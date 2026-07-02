<?php

namespace Modules\Akademik\Filament\Resources\Pelajaran\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\Pelajaran\PelajaranResource;
use Modules\Akademik\Models\Pelajaran;
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
                'export' => fn (Tenant $t) => Pelajaran::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with('guruPengampu')->orderBy('nama')->lazy()
                    ->map(fn (Pelajaran $p): array => [
                        'nama' => $p->nama, 'kode' => $p->kode, 'sks' => $p->sks,
                        'guru' => $p->guruPengampu?->email,
                    ]),
                'label' => 'Import Pelajaran',
                'service' => PelajaranImportService::class,
                'directory' => 'imports/akademik/pelajaran/uploads',
                'ability' => 'manage_akademik',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nama (wajib), kode (unik, opsional), sks, guru (email/nama pengampu, opsional). Pelajaran dengan kode/nama yang sudah ada dilewati. Aman diulang (dry-run dulu).',
            ]),
        ];
    }
}
