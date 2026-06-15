<?php

namespace Modules\Kesantrian\Services;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Kesantrian\Models\Santri;

/**
 * Membuat akun Portal Wali secara massal dari data santri yang sudah ada.
 *
 * KUNCI = NOMOR HP (keputusan owner 2026-06-15): login Portal Wali memakai
 * no. HP (PortalWaliIdentifierResolver mengecek users.phone). Akun dibuat untuk
 * santri aktif yang punya `no_hp_wali`; nomor dinormalisasi via PhoneNumber
 * (sumber kebenaran tunggal, sama dgn resolver login). Email opsional (disimpan
 * bila ada). Idempoten: wali dgn phone yang sudah berakun cukup ditautkan ulang.
 *
 * Diekstrak dari ImportWaliCommand agar dipakai ulang UI panel + command.
 * ASUMSI: tenancy sudah aktif.
 */
class WaliImportService
{
    /**
     * @return array{
     *   total:int, created:int, existing:int, no_hp:int,
     *   imported:int, skipped:int, failed:int,
     *   errors:array<int, array{row:int, ref:string, error:string}>,
     *   csv_path:?string
     * }
     */
    public function generate(string $tenantId, bool $dryRun = false): array
    {
        $stats = [
            'total' => 0, 'created' => 0, 'existing' => 0, 'no_hp' => 0,
            'imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [], 'csv_path' => null,
        ];
        $credentials = [];

        $santriQuery = Santri::query()
            ->where('status', 'aktif')
            ->whereNotNull('no_hp_wali')
            ->where('no_hp_wali', '!=', '');

        $santriQuery->chunkById(100, function ($santriList) use ($tenantId, $dryRun, &$stats, &$credentials): void {
            foreach ($santriList as $santri) {
                $stats['total']++;
                $phone = PhoneNumber::normalize((string) $santri->no_hp_wali);

                if ($phone === '') {
                    $stats['no_hp']++;

                    continue;
                }

                try {
                    $existingUser = User::query()->where('phone', $phone)->first();

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
                        $emailWali = trim((string) $santri->email_wali);

                        $user = User::create([
                            'tenant_id' => $tenantId,
                            'name' => $namaWali,
                            'phone' => $phone,
                            'email' => $emailWali !== '' ? strtolower($emailWali) : null,
                            'password' => Hash::make($password),
                        ]);
                        $user->assignRole('wali');
                        $santri->update(['wali_user_id' => $user->id]);

                        $credentials[] = [
                            $santri->nama, $santri->nis, $namaWali,
                            $phone, $emailWali ?: '-', $password,
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
        $stats['skipped'] = $stats['existing'] + $stats['no_hp'];

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
        fputcsv($buffer, ['Nama Santri', 'NIS', 'Nama Wali', 'No HP', 'Email', 'Password Sementara']);
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
