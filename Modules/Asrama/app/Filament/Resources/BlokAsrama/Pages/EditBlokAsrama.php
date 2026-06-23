<?php

namespace Modules\Asrama\Filament\Resources\BlokAsrama\Pages;

use Modules\Asrama\Filament\Resources\BlokAsrama\BlokAsramaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
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

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(BlokAsramaService::class)->simpan($data, $record);
    }
}

