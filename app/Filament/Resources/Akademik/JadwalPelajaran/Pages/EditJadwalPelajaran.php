<?php

namespace App\Filament\Resources\Akademik\JadwalPelajaran\Pages;

use App\Filament\Resources\Akademik\JadwalPelajaran\JadwalPelajaranResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Services\JadwalPelajaranService;

class EditJadwalPelajaran extends EditRecord
{
    protected static string $resource = JadwalPelajaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(JadwalPelajaranService::class)->simpan($data, $record);
    }
}
