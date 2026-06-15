<?php

namespace App\Filament\Resources\Akademik\Kurikulum\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Akademik\Kurikulum\KurikulumResource;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Models\Kurikulum;
use Modules\Akademik\Services\KurikulumImportService;

class ListKurikulum extends ListRecords
{
    use HasImportAction;

    protected static string $resource = KurikulumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Kurikulum'),
            ...$this->importActions([
                'entitas' => 'kurikulum',
                'template' => ['nama', 'lembaga'],
                'template_contoh' => ['Kurikulum 2024', 'Madrasah Aliyah'],
                'export' => fn (Tenant $t) => Kurikulum::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with('lembaga')->orderBy('nama')->lazy()
                    ->map(fn ($k): array => ['nama' => $k->nama, 'lembaga' => $k->lembaga?->nama]),
                'label' => 'Import Kurikulum',
                'service' => KurikulumImportService::class,
                'directory' => 'imports/akademik/kurikulum/uploads',
                'ability' => 'manage_akademik',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nama (wajib), lembaga (nama lembaga, wajib & harus sudah ada). Kurikulum dgn nama sama di lembaga itu dilewati. Dry-run dulu.',
            ]),
        ];
    }
}
