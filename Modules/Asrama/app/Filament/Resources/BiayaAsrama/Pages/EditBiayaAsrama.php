<?php

namespace Modules\Asrama\Filament\Resources\BiayaAsrama\Pages;

use Modules\Asrama\Filament\Resources\BiayaAsrama\BiayaAsramaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Asrama\Services\BiayaAsramaService;

class EditBiayaAsrama extends EditRecord
{
    protected static string $resource = BiayaAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(BiayaAsramaService::class)->sinkronkan($data, $record);
    }
}

