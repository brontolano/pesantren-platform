<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\PPDB\Services\PpdbFormFieldConfigurationService;
use RuntimeException;
use Tests\TestCase;

class PpdbFormFieldConfigurationServiceTest extends TestCase
{
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('TRUNCATE TABLE settings, domains, tenants RESTART IDENTITY CASCADE');

        $this->tenantA = Tenant::create([
            'id' => 'ppdb-form-a',
            'name' => 'Pesantren A',
            'is_active' => true,
        ]);
        $this->tenantA->domains()->create(['domain' => 'ppdb-form-a']);

        $this->tenantB = Tenant::create([
            'id' => 'ppdb-form-b',
            'name' => 'Pesantren B',
            'is_active' => true,
        ]);
        $this->tenantB->domains()->create(['domain' => 'ppdb-form-b']);
    }

    public function test_get_configuration_returns_defaults_when_setting_missing(): void
    {
        tenancy()->initialize($this->tenantA);

        $configuration = app(PpdbFormFieldConfigurationService::class)->getConfiguration();

        $this->assertSame('required', $configuration['nama']['mode']);
        $this->assertSame('optional', $configuration['nik']['mode']);
        // NISN default = 'staf' (diisi panitia, disembunyikan dari peserta).
        $this->assertSame('staf', $configuration['nisn']['mode']);
        // 'program_diminati' sudah dihapus dari biodata SPMB.
        $this->assertArrayNotHasKey('program_diminati', $configuration);

        tenancy()->end();
    }

    public function test_set_configuration_persists_overridden_modes_for_current_tenant(): void
    {
        tenancy()->initialize($this->tenantA);

        $service = app(PpdbFormFieldConfigurationService::class);
        $setting = $service->setConfiguration([
            'nik' => 'hidden',
            'email_wali' => 'required',
            'no_hp_ayah' => 'staf',
        ]);

        // Hanya override yang disimpan; sisanya pakai default saat dibaca.
        $this->assertSame(PpdbFormFieldConfigurationService::SETTING_KEY, $setting->key);
        $this->assertSame('hidden', $setting->value['fields']['nik']);
        $this->assertSame('required', $setting->value['fields']['email_wali']);
        $this->assertSame('staf', $setting->value['fields']['no_hp_ayah']);

        $config = $service->getConfiguration();
        $this->assertSame('hidden', $config['nik']['mode']);
        $this->assertSame('required', $config['email_wali']['mode']);
        $this->assertSame('staf', $config['no_hp_ayah']['mode']);
        $this->assertSame('required', $config['nama']['mode']); // default tetap

        tenancy()->end();
    }

    public function test_configuration_is_isolated_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);
        app(PpdbFormFieldConfigurationService::class)->setConfiguration([
            'nik' => 'hidden',
        ]);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        $configuration = app(PpdbFormFieldConfigurationService::class)->getConfiguration();

        $this->assertSame('optional', $configuration['nik']['mode']);
        $this->assertSame(0, Setting::query()->count());

        tenancy()->end();
    }

    public function test_set_configuration_rejects_unknown_field(): void
    {
        tenancy()->initialize($this->tenantA);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field PPDB tidak dikenal');

        app(PpdbFormFieldConfigurationService::class)->setConfiguration([
            'field_tidak_ada' => 'required',
        ]);
    }

    public function test_set_configuration_requires_active_tenant_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context belum aktif');

        app(PpdbFormFieldConfigurationService::class)->setConfiguration([
            'nik' => 'hidden',
        ]);
    }
}
