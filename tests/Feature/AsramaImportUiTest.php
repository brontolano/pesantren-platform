<?php

namespace Tests\Feature;

use App\Filament\Concerns\HasImportAction;
use Filament\Actions\Action;
use Modules\Asrama\Services\AsramaImportService;
use Tests\TestCase;

/**
 * Unit trait HasImportAction: memastikan buildImportAction menghasilkan
 * Filament Action yang benar (nama, label, hidden tanpa tenancy) — pondasi
 * importer self-service yang dipakai ulang lintas modul.
 */
class AsramaImportUiTest extends TestCase
{
    private function harness(): object
    {
        return new class
        {
            use HasImportAction;

            public function asrama(): Action
            {
                return $this->buildImportAction([
                    'entitas' => 'asrama',
                    'label' => 'Import Asrama',
                    'service' => AsramaImportService::class,
                    'directory' => 'imports/asrama/uploads',
                    'ability' => 'manage_santri',
                ]);
            }
        };
    }

    public function test_build_import_action_menghasilkan_action_bernama_benar(): void
    {
        $action = $this->harness()->asrama();

        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame('importAsrama', $action->getName());
        $this->assertSame('Import Asrama', $action->getLabel());
    }

    public function test_action_import_tersembunyi_saat_tenancy_belum_aktif(): void
    {
        // Tanpa tenancy aktif, visible() harus false (cegah import lintas-tenant).
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $action = $this->harness()->asrama();

        $this->assertFalse($action->isVisible());
    }
}
