<?php

namespace App\Filament\Resources\Tahfidz\Halaqah\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Tahfidz\Halaqah\HalaqahResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Tahfidz\Services\HalaqahImportService;

class ListHalaqah extends ListRecords
{
    use HasImportAction;

    protected static string $resource = HalaqahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Halaqah'),
            ...$this->importActions([
                'entitas' => 'halaqah',
                'template' => ['nama', 'musyrif', 'jenis'],
                'template_contoh' => ['Halaqah A', 'musyrif@contoh.test', 'tahfidz'],
                'export' => fn (\App\Models\Tenant $t) => \Modules\Tahfidz\Models\Halaqah::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with('musyrif')->orderBy('nama')->get()
                    ->map(fn ($h): array => ['nama' => $h->nama, 'musyrif' => $h->musyrif?->email, 'jenis' => $h->jenis]),
                'label' => 'Import Halaqah',
                'service' => HalaqahImportService::class,
                'directory' => 'imports/tahfidz/halaqah/uploads',
                'ability' => 'manage_santri',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nama (wajib), musyrif (email/nama akun musyrif, wajib), jenis (tahfidz/kitab/diniyah). Nama yang sudah ada dilewati. Dry-run dulu.',
            ]),
        ];
    }
}
