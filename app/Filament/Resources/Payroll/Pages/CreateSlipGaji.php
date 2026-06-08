<?php

namespace App\Filament\Resources\Payroll\Pages;

use App\Filament\Resources\Payroll\SlipGajiResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Services\PayrollService;

class CreateSlipGaji extends CreateRecord
{
    protected static string $resource = SlipGajiResource::class;

    /** Hitung slip lewat PayrollService (poin × tarif, PPh21) — bukan tulis langsung. */
    protected function handleRecordCreation(array $data): Model
    {
        $data['created_by'] = auth()->id();

        return app(PayrollService::class)->buatSlip($data);
    }
}
