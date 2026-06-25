<?php

namespace Modules\Pengadaan\Filament\Resources\Supplier\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Pengadaan\Filament\Resources\Supplier\SupplierResource;
use Modules\Pengadaan\Models\Supplier;
use Modules\Pengadaan\Services\SupplierImportService;

class ListSupplier extends ListRecords
{
    use HasImportAction;

    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Supplier'),
            ...$this->importActions([
                'entitas' => 'supplier',
                'template' => ['nama', 'telepon', 'alamat', 'npwp', 'bank_nama', 'bank_rekening', 'kehalalan'],
                'template_contoh' => ['CV Berkah Jaya', '08123456789', 'Jl. Merdeka 1', '01.234.567.8-901.000', 'BSI', '7001234567', 'halal'],
                'export' => fn (Tenant $t) => Supplier::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->orderBy('nama')->lazy()
                    ->map(fn ($s): array => [
                        'nama' => $s->nama,
                        'telepon' => $s->telepon,
                        'alamat' => $s->alamat,
                        'npwp' => $s->npwp,
                        'bank_nama' => $s->bank_nama,
                        'bank_rekening' => $s->bank_rekening,
                        'kehalalan' => $s->kehalalan,
                    ]),
                'label' => 'Import Supplier',
                'service' => SupplierImportService::class,
                'directory' => 'imports/pengadaan/supplier/uploads',
                'ability' => 'input_belanja',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nama (wajib), telepon, alamat, npwp, bank_nama, bank_rekening, kehalalan (halal/syubhat/non_halal, default halal). Supplier dgn nama sama dilewati (tak ditimpa). Dry-run dulu.',
            ]),
        ];
    }
}
