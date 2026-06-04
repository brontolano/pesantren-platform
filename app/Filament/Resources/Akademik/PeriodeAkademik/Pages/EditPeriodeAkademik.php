<?php

namespace App\Filament\Resources\Akademik\PeriodeAkademik\Pages;

use App\Filament\Resources\Akademik\PeriodeAkademik\PeriodeAkademikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Akademik\Services\PeriodeAkademikService;

class EditPeriodeAkademik extends EditRecord
{
    protected static string $resource = PeriodeAkademikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(PeriodeAkademikService::class)->simpan($data, $record);
    }
}
