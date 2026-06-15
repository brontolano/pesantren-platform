<?php

namespace App\Filament\Resources\Akademik\JadwalPelajaran\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Akademik\JadwalPelajaran\JadwalPelajaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Akademik\Services\JadwalImportService;

class ListJadwalPelajaran extends ListRecords
{
    use HasImportAction;

    protected static string $resource = JadwalPelajaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ...$this->importActions([
                'entitas' => 'jadwal',
                'template' => ['kelas', 'pelajaran', 'periode', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan'],
                'template_contoh' => ['VII-A', 'NHW', '2026/2027', 'senin', '07:00', '08:30', 'R1'],
                'export' => fn (\App\Models\Tenant $t) => \Modules\Akademik\Models\JadwalPelajaran::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with(['kelas', 'pelajaran', 'periodeAkademik'])->get()
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

