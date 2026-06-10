<?php

namespace App\Console\Commands;

use Stancl\Tenancy\Database\Models\Domain;
use App\Services\DomainVerificationService;
use Illuminate\Console\Command;

/**
 * Operasional verifikasi domain tenant:
 *   domain:verify contoh.sch.id            → cek bukti & tandai verified
 *   domain:verify contoh.sch.id --token    → terbitkan/tampilkan token + instruksi
 */
class DomainVerifyCommand extends Command
{
    protected $signature = 'domain:verify
        {domain : Nama domain persis seperti di tabel domains}
        {--token : Terbitkan/tampilkan token + instruksi pemasangan bukti}';

    protected $description = 'Verifikasi kepemilikan domain tenant (TXT DNS / .well-known)';

    public function handle(DomainVerificationService $svc): int
    {
        $domain = Domain::query()->where('domain', $this->argument('domain'))->first();
        if (! $domain) {
            $this->error('Domain tidak terdaftar di tabel domains.');

            return self::FAILURE;
        }

        if ($this->option('token')) {
            $token = $svc->issueToken($domain);
            $this->info("Token: {$token}");
            $this->line('Pasang SALAH SATU bukti berikut lalu jalankan ulang tanpa --token:');
            $this->line('  1) TXT record  : '.DomainVerificationService::TXT_PREFIX.$token);
            $this->line("  2) Berkas      : https://{$domain->domain}/.well-known/pesantren-verify.txt berisi token");

            return self::SUCCESS;
        }

        $hasil = $svc->verify($domain);
        if ($hasil['verified']) {
            $this->info("TERVERIFIKASI via {$hasil['method']} — verified_at diset.");

            return self::SUCCESS;
        }

        $this->warn('Belum terverifikasi — bukti tidak ditemukan (cek propagasi DNS / isi berkas).');

        return self::FAILURE;
    }
}
