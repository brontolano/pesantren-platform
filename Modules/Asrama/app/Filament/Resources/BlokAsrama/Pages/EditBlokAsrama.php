<?php

namespace Modules\Asrama\Filament\Resources\BlokAsrama\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Asrama\Filament\Resources\BlokAsrama\BlokAsramaResource;
use Modules\Asrama\Services\BlokAsramaService;

class EditBlokAsrama extends EditRecord
{
    protected static string $resource = BlokAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(BlokAsramaService::class)->simpan($data, $record);
    }
}
