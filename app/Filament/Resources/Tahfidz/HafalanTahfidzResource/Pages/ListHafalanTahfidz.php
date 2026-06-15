<?php

namespace App\Filament\Resources\Tahfidz\HafalanTahfidzResource\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Tahfidz\HafalanTahfidzResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Tahfidz\Services\HafalanImportService;

class ListHafalanTahfidz extends ListRecords
{
    use HasImportAction;

    protected static string $resource = HafalanTahfidzResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->buildImportAction([
                'entitas' => 'hafalan',
                'label' => 'Import Hafalan',
                'service' => HafalanImportService::class,
                'directory' => 'imports/tahfidz/hafalan/uploads',
                'ability' => 'manage_santri',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nis (wajib), halaqah (nama, opsional), surah, ayat_awal, ayat_akhir, status (direncanakan/proses/selesai), tanggal_target, tanggal_selesai, catatan. Baris (santri+surah+ayat) yang sama dilewati. Dry-run dulu.',
            ]),
        ];
    }
}
