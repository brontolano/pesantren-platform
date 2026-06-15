<?php

namespace App\Filament\Resources\Akademik\Pelajaran\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Akademik\Pelajaran\PelajaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Services\PelajaranImportService;

class ListPelajaran extends ListRecords
{
    use HasImportAction;

    protected static string $resource = PelajaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ...$this->importActions([
                'entitas' => 'pelajaran',
                'template' => ['nama', 'kode', 'sks', 'guru'],
                'template_contoh' => ['Nahwu', 'NHW', '2', 'ustadz@contoh.test'],
                'label' => 'Import Pelajaran',
                'service' => PelajaranImportService::class,
                'directory' => 'imports/akademik/pelajaran/uploads',
                'ability' => 'manage_akademik',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nama (wajib), kode (unik, opsional), sks, guru (email/nama pengampu, opsional). Pelajaran dengan kode/nama yang sudah ada dilewati. Aman diulang (dry-run dulu).',
            ]),
        ];
    }
}

