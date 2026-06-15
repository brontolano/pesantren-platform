<?php

namespace App\Filament\Resources\Akademik\Nilai\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Akademik\Nilai\NilaiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Services\NilaiImportService;

class ListNilai extends ListRecords
{
    use HasImportAction;

    protected static string $resource = NilaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->buildImportAction([
                'entitas' => 'nilai',
                'label' => 'Import Nilai',
                'service' => NilaiImportService::class,
                'directory' => 'imports/akademik/nilai/uploads',
                'ability' => 'manage_akademik',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nis, pelajaran (kode/nama), periode (opsional→aktif), kelas (opsional→kelas santri), nilai_harian, nilai_uts, nilai_uas, nilai_akhir, catatan. Nilai yang sudah ada akan DIPERBARUI (idempoten). Nilai kosong tidak menimpa. Dry-run dulu.',
                'extraSummary' => fn (array $s): string => sprintf('Diperbarui: %d', $s['updated'] ?? 0),
            ]),
        ];
    }
}

