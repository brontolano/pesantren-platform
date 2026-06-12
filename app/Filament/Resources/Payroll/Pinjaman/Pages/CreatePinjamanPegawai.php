<?php

namespace App\Filament\Resources\Payroll\Pinjaman\Pages;

use App\Filament\Resources\Payroll\Pinjaman\PinjamanPegawaiResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Payroll\Services\KasbonService;

class CreatePinjamanPegawai extends CreateRecord
{
    protected static string $resource = PinjamanPegawaiResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(KasbonService::class)->ajukan(
            (int) $data['employee_id'],
            (float) $data['nominal'],
            (float) $data['cicilan_bulanan'],
            $data['tanggal'] ?? null,
            $data['catatan'] ?? null,
            Auth::id(),
        );
    }
}
