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

        $expectedRoles = [
            'admin_pesantren',
            'guru',
            'guru_tahfidz',
            'kepala',
            'keuangan',
            'konselor',
            'pengelola_asrama',
            'pengelola_uks',
            'super_admin',
            'tu',
            'wali',
        ];

        $this->assertSame($expectedRoles, Role::query()->orderBy('name')->pluck('name')->values()->all());
        $this->assertTrue(Role::findByName('kepala')->hasPermissionTo('manage_tenant_settings'));
        $this->assertTrue(Role::findByName('wali')->hasPermissionTo('view_own_children'));
        $this->assertTrue(Role::findByName('admin_pesantren')->hasPermissionTo('manage_roles'));
    }
}
