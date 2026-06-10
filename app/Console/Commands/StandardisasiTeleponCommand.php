<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rapikan SEMUA nomor telepon tersimpan ke format kanonik 62… (App\Support\
 * PhoneNumber). Idempoten & aman: dry-run default (pakai --apply utk menulis),
 * hanya mengubah baris yang formatnya beda, guard hasTable/hasColumn, per
 * tenant (DB-per-tenant). Dijalankan dari koneksi central.
 */
class StandardisasiTeleponCommand extends Command
{
    protected $signature = 'telepon:standardisasi {tenant? : ID tenant tertentu (default: semua)} {--apply : Tulis perubahan (tanpa ini = dry-run)}';

    protected $description = 'Standardisasi semua nomor telepon tersimpan ke format 62… (idempoten, aman)';

    /** Kolom string biasa: tabel => kolom. */
    private const KOLOM = [
        'santri' => 'no_hp_wali',
        'calon_santri' => 'no_hp_wali',
        'supplier' => 'telepon',
        'users' => 'phone',
    ];

    /** Kolom JSON: tabel => [kolom_json => [key…]]. */
    private const JSON_KOLOM = [
        'calon_santri' => ['data_ortu' => ['no_hp_ayah', 'no_hp_ibu', 'no_hp_wali', 'no_hp_peserta']],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->warn($apply ? '⚠  MODE TULIS (--apply): perubahan akan disimpan.' : 'ℹ  DRY-RUN: tidak ada yang ditulis. Tambah --apply untuk menyimpan.');

        $tenants = $this->argument('tenant')
            ? Tenant::query()->whereKey($this->argument('tenant'))->get()
            : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('Tidak ada tenant.');

            return self::FAILURE;
        }

        $totalUbah = 0;
        foreach ($tenants as $tenant) {
            $totalUbah += $this->prosesTenant($tenant, $apply);
        }

        $this->newLine();
        $this->info(($apply ? 'SELESAI — ' : 'DRY-RUN — ')."total {$totalUbah} nomor ".($apply ? 'dirapikan.' : 'AKAN dirapikan (jalankan dgn --apply).'));

        return self::SUCCESS;
    }

    private function prosesTenant(Tenant $tenant, bool $apply): int
    {
        $ubah = 0;
        tenancy()->initialize($tenant);
        $this->line("── Tenant: <fg=cyan>{$tenant->getKey()}</> ──");

        try {
            $conn = DB::connection('tenant');

            // 1) Kolom string biasa.
            foreach (self::KOLOM as $tabel => $kolom) {
                if (! Schema::connection('tenant')->hasTable($tabel) || ! Schema::connection('tenant')->hasColumn($tabel, $kolom)) {
                    continue;
                }
                $n = 0;
                $contoh = null;
                $conn->table($tabel)->whereNotNull($kolom)->where($kolom, '!=', '')
                    ->orderBy('id')->chunkById(500, function ($rows) use (&$n, &$contoh, $conn, $tabel, $kolom, $apply) {
                        foreach ($rows as $row) {
                            $lama = (string) $row->{$kolom};
                            $baru = PhoneNumber::normalize($lama);
                            if ($baru !== '' && $baru !== $lama) {
                                $contoh ??= "{$lama} → {$baru}";
                                $n++;
                                if ($apply) {
                                    $conn->table($tabel)->where('id', $row->id)->update([$kolom => $baru]);
                                }
                            }
                        }
                    });
                if ($n > 0) {
                    $this->line("  {$tabel}.{$kolom}: <fg=yellow>{$n}</> (contoh: {$contoh})");
                    $ubah += $n;
                }
            }

            // 2) Kolom JSON (data_ortu dll).
            foreach (self::JSON_KOLOM as $tabel => $defs) {
                if (! Schema::connection('tenant')->hasTable($tabel)) {
                    continue;
                }
                foreach ($defs as $kolom => $keys) {
                    if (! Schema::connection('tenant')->hasColumn($tabel, $kolom)) {
                        continue;
                    }
                    $n = 0;
                    $contoh = null;
                    $conn->table($tabel)->whereNotNull($kolom)
                        ->orderBy('id')->chunkById(500, function ($rows) use (&$n, &$contoh, $conn, $tabel, $kolom, $keys, $apply) {
                            foreach ($rows as $row) {
                                $data = json_decode((string) $row->{$kolom}, true);
                                if (! is_array($data)) {
                                    continue;
                                }
                                $berubah = false;
                                foreach ($keys as $k) {
                                    if (! empty($data[$k]) && is_string($data[$k])) {
                                        $baru = PhoneNumber::normalize($data[$k]);
                                        if ($baru !== '' && $baru !== $data[$k]) {
                                            $contoh ??= "{$data[$k]} → {$baru}";
                                            $data[$k] = $baru;
                                            $berubah = true;
                                        }
                                    }
                                }
                                if ($berubah) {
                                    $n++;
                                    if ($apply) {
                                        $conn->table($tabel)->where('id', $row->id)->update([$kolom => json_encode($data)]);
                                    }
                                }
                            }
                        });
                    if ($n > 0) {
                        $this->line("  {$tabel}.{$kolom} (JSON): <fg=yellow>{$n}</> (contoh: {$contoh})");
                        $ubah += $n;
                    }
                }
            }

            // 3) Settings bernuansa telepon (key mengandung whatsapp/no_hp/telepon).
            if (Schema::connection('tenant')->hasTable('settings')) {
                $rows = $conn->table('settings')->where('key', '~*', '(whatsapp|no_hp|telepon|admin_wa)')->get();
                $n = 0;
                $contoh = null;
                foreach ($rows as $row) {
                    $val = (string) ($row->value ?? '');
                    // Setting value bisa JSON-encoded string; coba decode dulu.
                    $decoded = json_decode($val, true);
                    $raw = is_string($decoded) ? $decoded : $val;
                    $baru = PhoneNumber::normalize($raw);
                    if ($baru !== '' && $baru !== $raw && PhoneNumber::isValid($raw)) {
                        $contoh ??= "{$raw} → {$baru}";
                        $n++;
                        if ($apply) {
                            $simpan = is_string($decoded) ? json_encode($baru) : $baru;
                            $conn->table('settings')->where('id', $row->id)->update(['value' => $simpan]);
                        }
                    }
                }
                if ($n > 0) {
                    $this->line("  settings (telepon): <fg=yellow>{$n}</> (contoh: {$contoh})");
                    $ubah += $n;
                }
            }

            if ($ubah === 0) {
                $this->line('  <fg=green>sudah rapi — tidak ada perubahan.</>');
            }
        } catch (\Throwable $e) {
            $this->error('  GAGAL: '.$e->getMessage());
        } finally {
            tenancy()->end();
        }

        return $ubah;
    }
}
