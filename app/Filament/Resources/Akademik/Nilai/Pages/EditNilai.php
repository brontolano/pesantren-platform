<?php

namespace App\Filament\Resources\Akademik\Nilai\Pages;

use App\Filament\Resources\Akademik\Nilai\NilaiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Services\NilaiService;

class EditNilai extends EditRecord
{
    protected static string $resource = NilaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(NilaiService::class)->simpan($data, auth()->user(), $record);
    }
}
