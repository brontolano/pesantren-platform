<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_roles_are_seeded_completely(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Role inti yang WAJIB ada (termasuk peran Baitul Maal). Pakai cek subset
        // (array_diff) agar penambahan role baru tak membuat test rapuh.
        $expectedRoles = [
            'admin_pesantren', 'asatidz', 'bendahara', 'bendahara_pusat', 'dewan_pembina',
            'guru', 'guru_tahfidz', 'kasir', 'kepala', 'kepala_keuangan', 'keuangan',
            'konselor', 'operator_divisi', 'pengelola_asrama', 'pengelola_uks', 'peserta',
            'super_admin', 'tu', 'wali',
        ];

        $actualRoles = Role::query()->pluck('name')->all();
        $this->assertEmpty(
            array_diff($expectedRoles, $actualRoles),
            'Semua role inti wajib ter-seed: '.implode(', ', array_diff($expectedRoles, $actualRoles))
        );

        $this->assertTrue(Role::findByName('kepala')->hasPermissionTo('manage_tenant_settings'));
        $this->assertTrue(Role::findByName('wali')->hasPermissionTo('view_own_children'));
        $this->assertTrue(Role::findByName('admin_pesantren')->hasPermissionTo('manage_roles'));
        // Segregasi mutu (#27): kepala_keuangan punya gate approve_mutu.
        $this->assertTrue(Role::findByName('kepala_keuangan')->hasPermissionTo('approve_mutu'));
        // POS (POS-5): kasir & bendahara boleh operasikan POS.
        $this->assertTrue(Role::findByName('kasir')->hasPermissionTo('operate_pos'));
        $this->assertTrue(Role::findByName('bendahara')->hasPermissionTo('operate_pos'));
    }
}
