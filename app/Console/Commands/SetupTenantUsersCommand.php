<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Membuat user awal untuk tenant production.
 *
 * Penggunaan:
 *   php artisan tenant:setup-users asy-syifaa
 *   php artisan tenant:setup-users asy-syifaa --interactive  (tanya nama & email satu per satu)
 */
class SetupTenantUsersCommand extends Command
{
    protected $signature = 'tenant:setup-users
        {tenant : ID tenant (misal: asy-syifaa)}
        {--interactive : Tanya nama & email setiap user secara interaktif}
        {--force : Overwrite user yang sudah ada}';

    protected $description = 'Buat user awal (Admin, TU, Bendahara, Kepala) untuk tenant production';

    /** Default roles yang akan dibuat */
    private array $defaultRoles = [
        [
            'label'       => 'Admin Pesantren',
            'role'        => 'admin_pesantren',
            'name'        => 'Admin',
            'email_prefix'=> 'admin',
        ],
        [
            'label'       => 'Tata Usaha (TU)',
            'role'        => 'tu',
            'name'        => 'Tata Usaha',
            'email_prefix'=> 'tu',
        ],
        [
            'label'       => 'Bendahara',
            'role'        => 'keuangan',
            'name'        => 'Bendahara',
            'email_prefix'=> 'bendahara',
        ],
        [
            'label'       => 'Kepala Pesantren',
            'role'        => 'kepala',
            'name'        => 'Kepala',
            'email_prefix'=> 'kepala',
        ],
    ];

    public function handle(): int
    {
        $tenantId = (string) $this->argument('tenant');
        $tenant   = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' tidak ditemukan.");
            return self::FAILURE;
        }

        $this->info("Setup user untuk tenant: <fg=green>{$tenant->name}</>");
        $this->newLine();

        tenancy()->initialize($tenant);

        try {
            $created  = [];
            $skipped  = [];
            $domain   = "{$tenantId}.localhost"; // ganti dengan domain production saat live

            foreach ($this->defaultRoles as $roleConfig) {
                $email = "{$roleConfig['email_prefix']}@{$domain}";
                $name  = $roleConfig['name'];

                if ($this->option('interactive')) {
                    $name  = $this->ask("  Nama {$roleConfig['label']}", $name);
                    $email = $this->ask("  Email {$roleConfig['label']}", $email);
                }

                // Cek duplikat
                $existing = User::where('email', $email)->first();
                if ($existing && ! $this->option('force')) {
                    $this->line("  <fg=yellow>⚠ Skip:</> {$roleConfig['label']} ({$email}) — sudah ada");
                    $skipped[] = $email;
                    continue;
                }

                $password = Str::password(16); // Generate password kuat
                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'tenant_id' => $tenantId,
                        'name'      => $name,
                        'password'  => Hash::make($password),
                    ]
                );

                $user->syncRoles([$roleConfig['role']]);

                $created[] = [
                    'role'     => $roleConfig['label'],
                    'name'     => $name,
                    'email'    => $email,
                    'password' => $password,
                ];

                $this->line("  <fg=green>✓ Dibuat:</> {$roleConfig['label']} ({$email})");
            }
        } finally {
            tenancy()->end();
        }

        $this->newLine();

        if (! empty($created)) {
            $this->warn('⚠ SIMPAN KREDENSIAL BERIKUT DI TEMPAT AMAN:');
            $this->newLine();
            $this->table(
                ['Peran', 'Nama', 'Email', 'Password'],
                array_map(fn ($u) => [$u['role'], $u['name'], $u['email'], $u['password']], $created)
            );
            $this->newLine();
            $this->error('Password di atas hanya ditampilkan SEKALI. Segera ganti setelah login pertama!');
        }

        if (! empty($skipped)) {
            $this->info('User yang dilewati (sudah ada): ' . implode(', ', $skipped));
        }

        $this->newLine();
        $this->info('✅ Setup user selesai.');
        $this->info("Login ke panel admin: https://sistem.{$tenantId}.sch.id/admin");

        return self::SUCCESS;
    }
}
