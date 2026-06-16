<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Cadangan / pemulihan / reset KONTEN WEBSITE & CMS SAJA (sisi EKSTERNAL),
 * TERPISAH dari data operasional + admin (lihat TenantRecoveryService = sisi
 * INTERNAL). Pemisahan ini sengaja: pondok bisa mengelola website-nya tanpa
 * menyentuh data santri/keuangan, dan sebaliknya.
 *
 * Lingkup: tabel modul CMS + folder media-nya di disk publik tenant.
 *
 * Dijalankan di KONTEKS TENANT (panel /admin). Berbeda dgn TenantRecoveryService
 * (konteks central), path file di sini diambil via Storage::disk(...)->path()
 * yang SUDAH di-reroot bootstrapper tenancy ke storage/tenant<id>/app/public —
 * jadi TIDAK ada masalah sufiks-ganda. pg_dump memakai nama DB fisik tenant +
 * kredensial koneksi central (host/user/pass dibagi, password via PGPASSWORD).
 */
class WebsiteRecoveryService
{
    /** Tabel modul CMS. Urutan = aman untuk TRUNCATE (anak/galery_images dulu). */
    public const TABLES = ['gallery_images', 'galleries', 'articles', 'website_sections', 'website_settings'];

    /** Folder media CMS di disk publik (semua gambar artikel/galeri ada di bawahnya). */
    public const FILE_DIR = 'cms';

    protected function conn(): array
    {
        $name = config('tenancy.database.central_connection', config('database.default'));

        return config("database.connections.{$name}");
    }

    protected function dir(): string
    {
        $dir = storage_path('app/website-backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    protected function diskPublik(): string
    {
        return (string) config('media.disk', 'public');
    }

    /** Hanya disk lokal yang bisa di-tar; object storage (s3) di luar lingkup. */
    protected function diskLokal(): bool
    {
        return config("filesystems.disks.{$this->diskPublik()}.driver") === 'local';
    }

    /**
     * Path absolut folder media CMS. NULL bila disk media bukan lokal.
     * Konteks tenant → Storage::disk()->path() sudah ter-reroot benar.
     */
    protected function mediaDir(): ?string
    {
        if (! $this->diskLokal()) {
            return null;
        }

        return Storage::disk($this->diskPublik())->path(self::FILE_DIR);
    }

    /** Backup tabel CMS (gzipped SQL). Mengembalikan path file. */
    public function backupDb(Tenant $tenant): string
    {
        $db = $tenant->database()->getName();
        $c = $this->conn();
        $file = $this->dir().'/website_'.$db.'_'.now()->format('Ymd_His').'.sql.gz';
        $tabel = implode(' ', array_map(static fn (string $t): string => '-t '.escapeshellarg($t), self::TABLES));

        $cmd = sprintf(
            'pg_dump --clean --if-exists %s -h %s -p %s -U %s %s | gzip > %s',
            $tabel,
            escapeshellarg((string) $c['host']),
            escapeshellarg((string) $c['port']),
            escapeshellarg((string) $c['username']),
            escapeshellarg($db),
            escapeshellarg($file),
        );

        $this->run($cmd, $c['password']);

        return $file;
    }

    /** Backup media CMS (.tar.gz). NULL bila tak ada media / disk bukan lokal. */
    public function backupFiles(Tenant $tenant): ?string
    {
        $mediaDir = $this->mediaDir();
        if ($mediaDir === null || ! is_dir($mediaDir) || $this->kosong($mediaDir)) {
            return null;
        }

        $base = dirname($mediaDir);
        $file = $this->dir().'/website_'.$tenant->database()->getName().'_'.now()->format('Ymd_His').'_media.tar.gz';
        $cmd = sprintf(
            'tar -czf %s -C %s %s',
            escapeshellarg($file),
            escapeshellarg($base),
            escapeshellarg(self::FILE_DIR),
        );
        $this->run($cmd, null);

        return $file;
    }

    /** Restore tabel CMS dari gzipped SQL (hanya tabel CMS yang ditimpa). */
    public function restoreDb(Tenant $tenant, string $sqlGzPath): void
    {
        // GERBANG PEMISAHAN: tolak berkas yg menyentuh tabel di luar CMS (mis.
        // backup data keseluruhan yg ekstensinya sama .sql.gz) → cegah psql
        // menimpa data santri/keuangan/akun lewat halaman website.
        $this->validateDumpScope($sqlGzPath);

        $db = $tenant->database()->getName();
        $c = $this->conn();

        $cmd = sprintf(
            'gunzip -c %s | psql -v ON_ERROR_STOP=1 --single-transaction -h %s -p %s -U %s -d %s',
            escapeshellarg($sqlGzPath),
            escapeshellarg((string) $c['host']),
            escapeshellarg((string) $c['port']),
            escapeshellarg((string) $c['username']),
            escapeshellarg($db),
        );

        $this->run($cmd, $c['password'], 600);
    }

    /** Restore media CMS dari .tar.gz. Folder lama disisihkan (.bak) dulu. */
    public function restoreFiles(Tenant $tenant, string $tarPath): void
    {
        $mediaDir = $this->mediaDir();
        if ($mediaDir === null) {
            throw new \RuntimeException('Disk media bukan lokal — restore media via penyimpanan objek tidak didukung di sini.');
        }

        $bak = null;
        if (is_dir($mediaDir)) {
            $bak = $mediaDir.'.bak-'.now()->format('Ymd_His');
            @rename($mediaDir, $bak);
        }

        $base = dirname($mediaDir);
        $cmd = sprintf(
            'tar --no-absolute-names -xzf %s -C %s',
            escapeshellarg($tarPath),
            escapeshellarg($base),
        );
        $this->run($cmd, null, 600);

        // Sukses → buang folder .bak (di bawah disk publik, ikut terhitung kuota
        // tampilan). Bila extract GAGAL, run() melempar lebih dulu → .bak tetap
        // ada sebagai jalur pemulihan manual.
        if ($bak !== null && is_dir($bak)) {
            File::deleteDirectory($bak);
        }
    }

    /**
     * RESET KONTEN WEBSITE: backup-pengaman DULU → kosongkan tabel CMS + media.
     * Data operasional/admin TIDAK disentuh.
     *
     * @return array{db_backup:string, files_backup:?string}
     */
    public function resetWebsite(Tenant $tenant): array
    {
        $dbBackup = $this->backupDb($tenant);
        $filesBackup = $this->backupFiles($tenant);

        // Kosongkan tabel CMS saja (CASCADE membereskan FK gallery_images→galleries).
        // + bersihkan indeks media_files utk file CMS yg ikut terhapus, agar
        // akuntansi kuota & daftar Media tetap konsisten (TRUNCATE melewati event
        // model yg biasanya mengelola media_files).
        $tenant->run(function (): void {
            DB::statement('TRUNCATE TABLE '.implode(', ', self::TABLES).' RESTART IDENTITY CASCADE');
            try {
                DB::table('media_files')->where('path', 'like', self::FILE_DIR.'/%')->delete();
            } catch (\Throwable) {
                // tabel media_files tak ada di tenant ini → abaikan (non-kritis).
            }
        });

        // Hapus media CMS (sudah aman di $filesBackup).
        $mediaDir = $this->mediaDir();
        if ($mediaDir !== null && is_dir($mediaDir)) {
            File::deleteDirectory($mediaDir);
        }

        return ['db_backup' => $dbBackup, 'files_backup' => $filesBackup];
    }

    /**
     * Pastikan dump HANYA menyentuh tabel CMS (DROP/CREATE/COPY). Bila ada tabel
     * lain (mis. santri/users dari backup data keseluruhan), TOLAK — jaga pemisahan
     * eksternal/internal. FK "REFERENCES public.users" tidak dihitung (bukan
     * DROP/CREATE/COPY), jadi backup CMS yg sah tetap lolos.
     */
    protected function validateDumpScope(string $sqlGzPath): void
    {
        $allowed = array_flip(self::TABLES);
        $fh = gzopen($sqlGzPath, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Gagal membaca berkas backup (.sql.gz tidak valid).');
        }

        try {
            while (($line = gzgets($fh)) !== false) {
                if (preg_match('/^\s*(?:DROP TABLE IF EXISTS|CREATE TABLE|COPY)\s+(?:public\.)?"?([A-Za-z_][A-Za-z0-9_]*)"?/i', $line, $m)) {
                    $tabel = strtolower($m[1]);
                    if (! isset($allowed[$tabel])) {
                        throw new \RuntimeException(
                            "Berkas ini menyentuh tabel di luar konten website ('{$tabel}'). "
                            ."Pakai berkas hasil 'Backup Konten Website', BUKAN backup data keseluruhan."
                        );
                    }
                }
            }
        } finally {
            gzclose($fh);
        }
    }

    protected function kosong(string $dir): bool
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                return false;
            }
        }

        return true;
    }

    protected function run(string $cmd, ?string $password, int $timeout = 300): void
    {
        $process = Process::fromShellCommandline(
            $cmd,
            base_path(),
            ['PGPASSWORD' => (string) $password],
            null,
            $timeout,
        );
        $process->mustRun();
    }
}
