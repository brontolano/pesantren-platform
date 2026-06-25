<?php

namespace Modules\Akademik\Filament\Resources\Nilai\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Filament\Resources\Nilai\NilaiResource;
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(NilaiService::class)->simpan($data, auth()->user(), $record);
    }
}
