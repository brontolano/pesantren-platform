<?php

namespace Modules\Kesantrian\Console;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\SantriAccountService;
use Modules\Notifikasi\Services\NotifDispatcher;

/**
 * Onboarding santri aktif LAMA tanpa akun: buat akun login massal
 * (+ tautan akun wali). Opsi --kirim-wa: kirim kredensial SEMENTARA ke
 * no_hp_wali via NotifDispatcher (template KREDENSIAL_SANTRI, gated per tenant).
 */
class ProvisionAkunSantriCommand extends Command
{
    protected $signature = 'santri:provision-akun
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}
        {--kirim-wa : Kirim kredensial sementara ke wali via WhatsApp}';

    protected $description = 'Buat akun login utk santri aktif yang belum punya (massal, idempoten)';

    public function handle(): int
    {
        $target = $this->argument('tenant');
        $kirimWa = (bool) $this->option('kirim-wa');

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        $rows = [];
        foreach ($tenants as $tenant) {
            $hasil = $tenant->run(fn (): array => $this->provisionTenantAktif($kirimWa));
            $rows[] = [$tenant->getKey(), $hasil['kandidat'], $hasil['dibuat'], $hasil['wa']];
        }

        $this->table(['Tenant', 'Tanpa Akun', 'Akun Dibuat', 'WA Terkirim'], $rows);

        return self::SUCCESS;
    }

    /** @return array{kandidat:int, dibuat:int, wa:int} */
    private function provisionTenantAktif(bool $kirimWa): array
    {
        $svc = app(SantriAccountService::class);
        $kandidat = Santri::query()->where('status', 'aktif')->whereNull('user_id')->get();

        $dibuat = 0;
        $wa = 0;
        foreach ($kandidat as $santri) {
            try {
                $user = $svc->provision($santri);
                $svc->tautkanWali($santri->fresh());
                $dibuat++;

                if ($kirimWa && filled($santri->no_hp_wali)) {
                    // Kredensial SEMENTARA: password baru yang diketahui (acak),
                    // wajib diganti setelah login pertama.
                    $sementara = Str::password(10, symbols: false);
                    $user->forceFill(['password' => $sementara])->save();

                    $hasil = app(NotifDispatcher::class)->kirim('kredensial_santri', [
                        'wa' => [$santri->no_hp_wali],
                        'data' => [
                            'nama_santri' => (string) $santri->nama,
                            'nis' => (string) $santri->nis,
                            'username' => (string) $user->email,
                            'password' => $sementara,
                            'nama_pesantren' => Setting::getTenantStringValue('branding_name')
                                ?? (tenant('name') ?? 'Pesantren'),
                        ],
                    ]);
                    $wa += (int) ($hasil['terkirim'] ?? 0);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return ['kandidat' => $kandidat->count(), 'dibuat' => $dibuat, 'wa' => $wa];
    }
}
