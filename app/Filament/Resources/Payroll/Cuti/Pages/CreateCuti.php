<?php

namespace App\Filament\Resources\Payroll\Cuti\Pages;

use App\Filament\Resources\Payroll\Cuti\CutiResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Payroll\Services\CutiService;

class CreateCuti extends CreateRecord
{
    protected static string $resource = CutiResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CutiService::class)->ajukan(
            (int) $data['employee_id'], $data['jenis'] ?? 'tahunan',
            $data['tanggal_mulai'], $data['tanggal_selesai'], $data['alasan'] ?? null, Auth::id(),
            array_key_exists('is_berbayar', $data) ? (bool) $data['is_berbayar'] : null,
        );
    }
}
