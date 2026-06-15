<?php

namespace App\Filament\Resources\Akademik\Nilai\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Akademik\Nilai\NilaiResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\NilaiImportService;
use Modules\Kesantrian\Models\Kelas;

class ListNilai extends ListRecords
{
    use HasImportAction;

    protected static string $resource = NilaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('cetakDaftarNilai')
                ->label('Cetak Daftar Nilai')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn (): bool => tenancy()->initialized && (auth()->user()?->canAny(['view_santri', 'manage_akademik']) ?? false))
                ->schema([
                    Select::make('kelas_id')
                        ->label('Kelas')
                        ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                        ->required()
                        ->searchable(),
                    Select::make('periode_id')
                        ->label('Periode (opsional)')
                        ->options(fn () => PeriodeAkademik::query()->orderByDesc('tahun_akademik')->pluck('nama', 'id'))
                        ->searchable(),
                ])
                ->action(fn (array $data) => redirect()->route('tenant.cetak.nilai-legger', array_filter([
                    'kelas_id' => $data['kelas_id'],
                    'periode_id' => $data['periode_id'] ?? null,
                ]))),
            ...$this->importActions([
                'entitas' => 'nilai',
                'template' => ['nis', 'pelajaran', 'periode', 'kelas', 'nilai_harian', 'nilai_uts', 'nilai_uas', 'nilai_akhir', 'catatan'],
                'template_contoh' => ['2024001', 'NHW', '2026/2027', 'VII-A', '80', '75', '90', '82', ''],
                'export' => fn (\App\Models\Tenant $t) => \Modules\Akademik\Models\Nilai::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with(['santri', 'pelajaran', 'periodeAkademik', 'kelas'])->get()
                    ->map(fn ($n): array => ['nis' => $n->santri?->nis, 'pelajaran' => $n->pelajaran?->kode, 'periode' => $n->periodeAkademik?->tahun_akademik, 'kelas' => $n->kelas?->nama_kelas, 'nilai_harian' => $n->nilai_harian, 'nilai_uts' => $n->nilai_uts, 'nilai_uas' => $n->nilai_uas, 'nilai_akhir' => $n->nilai_akhir, 'catatan' => $n->catatan]),
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

