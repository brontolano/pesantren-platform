<?php

namespace App\Filament\Resources\Asrama\KamarAsrama\Pages;

use App\Filament\Resources\Asrama\KamarAsrama\KamarAsramaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
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

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(KamarAsramaService::class)->simpan($data, $record);
    }
}

