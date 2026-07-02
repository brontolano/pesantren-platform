<?php

namespace Modules\Akademik\Filament\Resources\JadwalPelajaran\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Filament\Resources\JadwalPelajaran\JadwalPelajaranResource;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Services\JadwalImportService;
use Modules\Kesantrian\Models\Kelas;

class ListJadwalPelajaran extends ListRecords
{
    use HasImportAction;

    protected static string $resource = JadwalPelajaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('cetakJadwalKelas')
                ->label('Cetak Jadwal')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn (): bool => tenancy()->initialized && (auth()->user()?->canAny(['view_santri', 'manage_akademik']) ?? false))
                ->schema([
                    Select::make('kelas_id')
                        ->label('Kelas')
                        ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                        ->required()
                        ->searchable(),
                ])
                ->action(fn (array $data) => redirect()->route('tenant.cetak.jadwal-kelas', ['kelas_id' => $data['kelas_id']])),
            ...$this->importActions([
                'entitas' => 'jadwal',
                'template' => ['kelas', 'pelajaran', 'periode', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan'],
                'template_contoh' => ['VII-A', 'NHW', '2026/2027', 'senin', '07:00', '08:30', 'R1'],
                'export' => fn (Tenant $t) => JadwalPelajaran::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with(['kelas', 'pelajaran', 'periodeAkademik'])->lazy()
                    ->map(fn ($j): array => ['kelas' => $j->kelas?->nama_kelas, 'pelajaran' => $j->pelajaran?->kode, 'periode' => $j->periodeAkademik?->tahun_akademik, 'hari' => $j->hari, 'jam_mulai' => $j->jam_mulai, 'jam_selesai' => $j->jam_selesai, 'ruangan' => $j->ruangan]),
                'label' => 'Import Jadwal',
                'service' => JadwalImportService::class,
                'directory' => 'imports/akademik/jadwal/uploads',
                'ability' => 'manage_akademik',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: kelas (nama kelas), pelajaran (kode/nama), periode (opsional, default periode aktif), hari (senin..ahad), jam_mulai, jam_selesai (HH:MM), ruangan. Kelas & pelajaran harus sudah ada. Slot bentrok ditolak. Aman diulang (dry-run dulu).',
            ]),
        ];
    }
}
