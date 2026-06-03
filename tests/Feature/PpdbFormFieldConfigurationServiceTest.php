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
        $this->assertSame('required', $configuration['program_diminati']['mode']);

        tenancy()->end();
    }

    public function test_set_configuration_persists_normalized_modes_for_current_tenant(): void
    {
        tenancy()->initialize($this->tenantA);

        $setting = app(PpdbFormFieldConfigurationService::class)->setConfiguration([
            'nik' => 'hidden',
            'email_wali' => 'required',
            'catatan_tambahan' => 'hidden',
        ]);

        $this->assertSame(PpdbFormFieldConfigurationService::SETTING_KEY, $setting->key);
        $this->assertSame('hidden', $setting->value['fields']['nik']);
        $this->assertSame('required', $setting->value['fields']['email_wali']);
        $this->assertSame('required', $setting->value['fields']['nama']);

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
