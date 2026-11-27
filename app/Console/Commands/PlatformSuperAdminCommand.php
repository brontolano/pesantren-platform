<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PlatformSuperAdminCommand extends Command
{
    protected $signature = 'platform:super-admin
        {email : Email akun pusat Pesantren.online}
        {--name= : Nama pemilik akun platform}
        {--reset-password : Atur ulang sandi akun superadmin pusat yang sudah ada}';

    protected $description = 'Buat atau pulihkan akun superadmin pusat Pesantren.online';

    public function handle(): int
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        $email = strtolower(trim((string) $this->argument('email')));
        $emailValidation = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']]);

        if ($emailValidation->fails()) {
            $this->error('Email superadmin tidak valid.');

            return self::FAILURE;
        }

        $existing = User::on($central)->where('email', $email)->first();

        if ($existing && $existing->tenant_id !== null) {
            $this->error('Email tersebut terikat ke akun tenant. Gunakan email platform yang berbeda.');

            return self::FAILURE;
        }

        if ($existing && ! $existing->hasRole('super_admin')) {
            $this->error('Email tersebut sudah dipakai akun pusat biasa. Periksa akun secara manual sebelum memberikan akses superadmin.');

            return self::FAILURE;
        }

        if ($existing && ! $this->option('reset-password')) {
            $this->line('Akun superadmin pusat sudah siap. Kata sandi tidak diubah.');

            return self::SUCCESS;
        }

        $name = trim((string) $this->option('name'));
        if ($name === '') {
            $name = trim((string) $this->ask('Nama pemilik akun platform'));
        }
        if ($name === '') {
            $this->error('Nama wajib diisi.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Kata sandi baru (minimal 12 karakter)');
        $confirmation = (string) $this->secret('Ulangi kata sandi');
        if (mb_strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->error('Kata sandi harus minimal 12 karakter dan kedua isian harus sama.');

            return self::FAILURE;
        }

        $user = DB::connection($central)->transaction(function () use ($central, $email, $name, $password, $existing): User {
            $role = Role::on($central)->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
            $user = $existing ?? new User;
            $user->setConnection($central);
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'tenant_id' => null,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $user->save();
            $user->assignRole($role);

            return $user;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info($existing ? 'Kata sandi akun superadmin pusat berhasil diatur ulang.' : 'Akun superadmin pusat berhasil dibuat.');
        $this->line('Email: '.$user->email);
        $this->warn('Sandi tidak ditampilkan. Simpan sandi yang Anda masukkan di pengelola kata sandi.');
        $this->line('Panel platform: /platform');
        $this->line('Login mewajibkan MFA email. Pastikan pengiriman email server sudah dikonfigurasi.');

        return self::SUCCESS;
    }
}
