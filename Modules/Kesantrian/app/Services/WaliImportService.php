<?php

namespace Modules\Kesantrian\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Kesantrian\Models\Santri;

/**
 * Membuat akun Portal Wali secara massal dari data santri yang sudah ada
 * (santri aktif yang punya email_wali). Idempoten: santri yang walinya sudah
 * beremail-akun cukup ditautkan ulang, tidak membuat user dobel.
 *
 * Diekstrak dari ImportWaliCommand agar bisa dipakai ulang oleh UI panel
 * (tombol "Buat Akun Wali") maupun command. ASUMSI: tenancy sudah aktif.
 *
 * Kontrak return mengikuti gaya importer lain (imported/skipped/failed/errors)
 * plus rincian khas wali (created/existing/no_email + path csv kredensial).
 */
class WaliImportService
{
    /**
     * @return array{
     *   total:int, created:int, existing:int, no_email:int,
     *   imported:int, skipped:int, failed:int,
     *   errors:array<int, array{row:int, ref:string, error:string}>,
     *   csv_path:?string
     * }
     */
    public function generate(string $tenantId, bool $dryRun = false): array
    {
        $stats = [
            'total' => 0, 'created' => 0, 'existing' => 0, 'no_email' => 0,
            'imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [], 'csv_path' => null,
        ];
        $credentials = [];

        $santriQuery = Santri::query()
            ->where('status', 'aktif')
            ->whereNotNull('email_wali')
            ->where('email_wali', '!=', '');

        $santriQuery->chunkById(100, function ($santriList) use ($tenantId, $dryRun, &$stats, &$credentials): void {
            foreach ($santriList as $santri) {
                $stats['total']++;
                $emailWali = strtolower(trim((string) $santri->email_wali));

                if ($emailWali === '') {
                    $stats['no_email']++;

                    continue;
                }

                try {
                    $existingUser = User::query()->where('email', $emailWali)->first();

                    if ($existingUser !== null) {
                        if (! $dryRun && $santri->wali_user_id !== $existingUser->id) {
                            $santri->update(['wali_user_id' => $existingUser->id]);
                        }
                        $stats['existing']++;

                        continue;
                    }

                    if (! $dryRun) {
                        $password = Str::password(12);
                        $namaWali = $santri->nama_ayah ?: $santri->nama_ibu ?: ('Wali '.$santri->nama);

                        $user = User::create([
                            'tenant_id' => $tenantId,
                            'name' => $namaWali,
                            'email' => $emailWali,
                            'password' => Hash::make($password),
                        ]);
                        $user->assignRole('wali');
                        $santri->update(['wali_user_id' => $user->id]);

                        $credentials[] = [
                            $santri->nama, $santri->nis, $namaWali,
                            $emailWali, $santri->no_hp_wali ?? '-', $password,
                        ];
                    }

                    $stats['created']++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'row' => $stats['total'],
                        'ref' => (string) $santri->nis,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        // Selaraskan ke kontrak importer umum.
        $stats['imported'] = $stats['created'];
        $stats['skipped'] = $stats['existing'] + $stats['no_email'];

        if (! $dryRun && $credentials !== []) {
            $stats['csv_path'] = $this->saveCsv($credentials, $tenantId);
        }

        return $stats;
    }

    /**
     * @param  array<int, array<int, string>>  $credentials
     */
    private function saveCsv(array $credentials, string $tenantId): string
    {
        $filename = "wali-import-{$tenantId}-".now()->format('Ymd-His').'.csv';

        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, ['Nama Santri', 'NIS', 'Nama Wali', 'Email', 'No HP', 'Password Sementara']);
        foreach ($credentials as $row) {
            fputcsv($buffer, $row);
        }
        rewind($buffer);
        $csv = (string) stream_get_contents($buffer);
        fclose($buffer);

        // Storage 'local' = tenancy-aware (root storage/tenant{id}/app); auto-buat folder.
        Storage::disk('local')->put($filename, $csv);

        return Storage::disk('local')->path($filename);
    }
}
