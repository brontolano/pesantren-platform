<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Backup / restore / RESET database + file fisik milik SATU tenant.
 * Dipakai dari panel platform (super_admin) untuk monitoring & recovery.
 *
 * Semua perintah shell (pg_dump/psql/tar) dijalankan di dalam container app
 * (punya postgresql-client-16 + tar/gzip). Nama DB SELALU dari
 * $tenant->database()->getName(); kredensial dari koneksi central (host/user/pass
 * dibagi, hanya nama DB per-tenant). Password via env PGPASSWORD, tak pernah di argv.
 */
class TenantRecoveryService
{
    protected function conn(): array
    {
        $name = config('tenancy.database.central_connection', config('database.default'));

        return config("database.connections.{$name}");
    }

    protected function dir(): string
    {
        $dir = storage_path('app/tenant-backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** Direktori file unggahan fisik tenant (konteks central), mis. storage/tenant<id>. */
    protected function tenantStorageDir(Tenant $tenant): string
    {
        $suffix = config('tenancy.filesystem.suffix_base', 'tenant').$tenant->getTenantKey();

        return storage_path($suffix);
    }

    /**
     * Buat backup (gzipped SQL) database tenant. Mengembalikan path file.
     */
    public function backup(Tenant $tenant): string
    {
        $db = $tenant->database()->getName();
        $c = $this->conn();
        $file = $this->dir().'/'.$db.'_'.now()->format('Ymd_His').'.sql.gz';

        $cmd = sprintf(
            'pg_dump --clean --if-exists -h %s -p %s -U %s %s | gzip > %s',
            escapeshellarg((string) $c['host']),
            escapeshellarg((string) $c['port']),
            escapeshellarg((string) $c['username']),
            escapeshellarg($db),
            escapeshellarg($file),
        );

        $this->run($cmd, $c['password']);

        return $file;
    }

    /**
     * Backup file unggahan tenant (.tar.gz). NULL bila tenant belum punya file.
     */
    public function backupFiles(Tenant $tenant): ?string
    {
        $suffix = config('tenancy.filesystem.suffix_base', 'tenant').$tenant->getTenantKey();
        $dir = storage_path($suffix);
        if (! is_dir($dir) || $this->dirKosong($dir)) {
            return null;
        }

        $file = $this->dir().'/'.$tenant->database()->getName().'_'.now()->format('Ymd_His').'_files.tar.gz';
        $cmd = sprintf(
            'tar -czf %s -C %s %s',
            escapeshellarg($file),
            escapeshellarg(storage_path()),
            escapeshellarg($suffix),
        );
        $this->run($cmd, null);

        return $file;
    }

    /**
     * Restore database tenant dari file gzipped SQL.
     */
    public function restore(Tenant $tenant, string $sqlGzPath): void
    {
        $db = $tenant->database()->getName();
        $c = $this->conn();

        // --single-transaction: gagal di tengah = rollback penuh (tak ada DB setengah-restore).
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

    /**
     * Restore file unggahan tenant dari .tar.gz. Direktori lama diberi sufiks
     * .bak-<ts> dulu (tak langsung dihapus), lalu arsip diekstrak.
     */
    public function restoreFiles(Tenant $tenant, string $tarPath): void
    {
        $dir = $this->tenantStorageDir($tenant);
        if (is_dir($dir)) {
            @rename($dir, $dir.'.bak-'.now()->format('Ymd_His'));
        }

        // --no-absolute-names + buang '..' : cegah path-traversal arsip jahat keluar storage.
        $cmd = sprintf(
            'tar --no-absolute-names -xzf %s -C %s',
            escapeshellarg($tarPath),
            escapeshellarg(storage_path()),
        );
        $this->run($cmd, null, 600);
    }

    /**
     * RESET keseluruhan data SATU tenant ke kondisi awal (kosong + fondasi).
     *
     * URUTAN AMAN (backup-first, tak bisa dibatalkan setelah wipe):
     *  1) Backup pengaman DB + file (selalu) — path dikembalikan utk rollback.
     *  2) DROP SCHEMA public CASCADE + CREATE SCHEMA (di DB FISIK tenant itu saja)
     *     + pasang ulang ekstensi vector sebelum migrasi (toleran).
     *  3) Migrasi ulang skema tenant (tenants:migrate).
     *  4) Hapus file unggahan (sudah diback-up di langkah 1).
     *  5) Reseed fondasi via TenantProvisioner (peran, COA/Ledger, branding,
     *     modul, admin baru) — IDEMPOTEN, sumber tunggal sama spt buat tenant.
     *
     * @return array{db_backup:string, files_backup:?string, admin_email:?string, admin_password:?string}
     */
    public function reset(Tenant $tenant, ?string $adminEmail = null): array
    {
        // 1) Backup pengaman LEBIH DULU (wajib). Lempar bila gagal → wipe batal.
        $dbBackup = $this->backup($tenant);
        $filesBackup = $this->backupFiles($tenant);

        // 2) Kosongkan skema DB fisik tenant ini saja.
        $tenant->run(function (): void {
            DB::unprepared('DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;');
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            } catch (\Throwable) {
                // Server tanpa pgvector → migrasi AI/RAG toleran (pola TenantProvisioner).
            }
        });

        // 3) Migrasi ulang skema tenant (pola TenantMigrateAllCommand).
        //    Bila migrate GAGAL: hentikan SEBELUM hapus file — backup ($dbBackup/$filesBackup)
        //    jadi jalur pemulihan; jangan tinggalkan skema rusak + file terhapus.
        $exit = Artisan::call('tenants:migrate', ['--tenants' => [$tenant->getKey()]]);
        if ($exit !== 0) {
            throw new \RuntimeException(
                "Migrasi tenant gagal saat reset (exit {$exit}). Data BELUM dihapus dari storage; "
                ."pulihkan DB dari backup: {$dbBackup}".($filesBackup ? " & file: {$filesBackup}" : '')
            );
        }

        // 4) Hapus file unggahan (sudah aman di $filesBackup).
        $this->clearFiles($tenant);

        // 5) Reseed fondasi + admin baru (idempoten, sumber tunggal).
        $prov = app(TenantProvisioner::class)->provision($tenant, $adminEmail);

        return [
            'db_backup' => $dbBackup,
            'files_backup' => $filesBackup,
            'admin_email' => $prov['email'] ?? null,
            'admin_password' => $prov['password'] ?? null,
        ];
    }

    /**
     * HAPUS PERMANEN satu tenant: backup pengaman dulu, lalu hapus baris central
     * (domains + ai_credit_ledger + tenant) dan DROP database fisiknya.
     *
     * ai_credit_ledger append-only menolak DELETE biasa; di sini kita SET GUC
     * `app.ledger_purge='on'` di dalam transaksi agar cascade penghapusan tenant
     * boleh memurnikan ledger (lihat migrasi ..._allow_authorized_purge). UPDATE
     * tetap mutlak dilarang.
     *
     * @return array{db_backup: string, files_backup: ?string}
     */
    public function purge(Tenant $tenant): array
    {
        // Pastikan tidak dalam konteks tenant (default = central) sebelum hapus.
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        $dbBackup = $this->backup($tenant);
        $filesBackup = $this->backupFiles($tenant);

        // Hapus tenant: cascade central (domains + ai_credit_ledger) + stancl DROP
        // DATABASE fisik. ai_credit_ledger append-only → butuh GUC app.ledger_purge.
        // Pakai SET sesi (BUKAN SET LOCAL/transaksi) karena stancl menjalankan
        // DROP DATABASE yang TAK BOLEH di dalam transaksi. RESET di finally agar
        // izin purge tak bocor ke operasi lain pada koneksi yang sama.
        try {
            DB::statement("SET app.ledger_purge = 'on'");
            $tenant->delete();
        } finally {
            DB::statement("SET app.ledger_purge = 'off'");
        }

        // Hapus file unggahan fisik (aman tersimpan di $filesBackup).
        $this->clearFiles($tenant);

        return ['db_backup' => $dbBackup, 'files_backup' => $filesBackup];
    }

    /**
     * Ukuran database tenant (human readable), mis. "8.5 MB".
     */
    public function size(Tenant $tenant): ?string
    {
        try {
            $db = $tenant->database()->getName();
            // Nama DB diinterpolasi ke SQL → batasi ke pola aman (whitelist), cegah injeksi
            // bila skema penamaan tenant berubah di kemudian hari.
            if (! preg_match('/^[A-Za-z0-9_]+$/', $db)) {
                return null;
            }
            $c = $this->conn();
            $process = Process::fromShellCommandline(
                sprintf(
                    'psql -tAc %s -h %s -p %s -U %s -d postgres',
                    escapeshellarg("SELECT pg_size_pretty(pg_database_size('{$db}'))"),
                    escapeshellarg((string) $c['host']),
                    escapeshellarg((string) $c['port']),
                    escapeshellarg((string) $c['username']),
                ),
                base_path(),
                ['PGPASSWORD' => $c['password']],
                null,
                30,
            );
            $process->mustRun();

            return trim($process->getOutput()) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function clearFiles(Tenant $tenant): void
    {
        $dir = $this->tenantStorageDir($tenant);
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }

    protected function dirKosong(string $dir): bool
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
