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
            $this->buildImportAction([
                'entitas' => 'jadwal',
                'label' => 'Import Jadwal',
                'service' => JadwalImportService::class,
                'directory' => 'imports/akademik/jadwal/uploads',
                'ability' => 'manage_akademik',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: kelas (nama kelas), pelajaran (kode/nama), periode (opsional, default periode aktif), hari (senin..ahad), jam_mulai, jam_selesai (HH:MM), ruangan. Kelas & pelajaran harus sudah ada. Slot bentrok ditolak. Aman diulang (dry-run dulu).',
            ]),
        ];
    }
}

