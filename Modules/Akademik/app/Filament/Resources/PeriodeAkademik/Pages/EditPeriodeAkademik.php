<?php

namespace Modules\Akademik\Filament\Resources\PeriodeAkademik\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Filament\Resources\PeriodeAkademik\PeriodeAkademikResource;
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(PeriodeAkademikService::class)->simpan($data, $record);
    }
}
