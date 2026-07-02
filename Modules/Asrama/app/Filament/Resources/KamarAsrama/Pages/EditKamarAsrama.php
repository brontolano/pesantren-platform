<?php

namespace Modules\Asrama\Filament\Resources\KamarAsrama\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Asrama\Filament\Resources\KamarAsrama\KamarAsramaResource;
use Modules\Asrama\Services\KamarAsramaService;

class EditKamarAsrama extends EditRecord
{
    protected static string $resource = KamarAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(KamarAsramaService::class)->simpan($data, $record);
    }
}
