<?php

namespace App\Filament\Resources\Kesehatan\Obat\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Kesehatan\ObatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Kesehatan\Services\ObatImportService;

class ListObat extends ListRecords
{
    use HasImportAction;

    protected static string $resource = ObatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->buildImportAction([
                'entitas' => 'obat',
                'label' => 'Import Obat',
                'service' => ObatImportService::class,
                'directory' => 'imports/kesehatan/obat/uploads',
                'ability' => 'manage_santri',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nama (wajib, unik), satuan, stok (stok awal), ambang_menipis. Nama yang sudah ada dilewati. Dry-run dulu.',
            ]),
        ];
    }
}
