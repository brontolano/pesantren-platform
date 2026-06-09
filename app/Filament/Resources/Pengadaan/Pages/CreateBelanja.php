<?php

namespace App\Filament\Resources\Pengadaan\Pages;

use App\Filament\Resources\Pengadaan\BelanjaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Pengadaan\Services\BelanjaService;

class CreateBelanja extends CreateRecord
{
    protected static string $resource = BelanjaResource::class;

    /**
     * Simpan lewat BelanjaService::buat agar pajak terhitung otomatis
     * (tidak menulis langsung ke tabel belanja).
     */
    protected function handleRecordCreation(array $data): Model
    {
        $data['created_by'] = auth()->id();

        return app(BelanjaService::class)->buat($data);
    }
}
