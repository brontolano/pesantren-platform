<?php

namespace App\Filament\Resources\Kesehatan\Obat\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Kesehatan\ObatResource;
use Filament\Actions\Action;
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
            Action::make('cetakStokObat')
                ->label('Cetak Stok')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('tenant.cetak.stok-obat', [], false))
                ->openUrlInNewTab()
                ->visible(fn (): bool => tenancy()->initialized && (auth()->user()?->can('manage_santri') ?? false)),
            ...$this->importActions([
                'entitas' => 'obat',
                'template' => ['nama', 'satuan', 'stok', 'ambang_menipis'],
                'template_contoh' => ['Paracetamol', 'tablet', '100', '20'],
                'export' => fn (\App\Models\Tenant $t) => \Modules\Kesehatan\Models\Obat::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->orderBy('nama')->get()
                    ->map(fn ($o): array => ['nama' => $o->nama, 'satuan' => $o->satuan, 'stok' => $o->stok, 'ambang_menipis' => $o->ambang_menipis]),
                'label' => 'Import Obat',
                'service' => ObatImportService::class,
                'directory' => 'imports/kesehatan/obat/uploads',
                'ability' => 'manage_santri',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nama (wajib, unik), satuan, stok (stok awal), ambang_menipis. Nama yang sudah ada dilewati. Dry-run dulu.',
            ]),
        ];
    }
}
