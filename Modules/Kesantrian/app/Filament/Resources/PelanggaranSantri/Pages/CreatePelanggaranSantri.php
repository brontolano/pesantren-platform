<?php

namespace Modules\Kesantrian\Filament\Resources\PelanggaranSantri\Pages;

use Modules\Kesantrian\Filament\Resources\PelanggaranSantri\PelanggaranSantriResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;

class CreatePelanggaranSantri extends CreateRecord
{
    protected static string $resource = PelanggaranSantriResource::class;

    /**
     * Pencatatan WAJIB lewat DisiplinService — status awal mengikuti saklar
     * kebijakan verifikasi 2-lapis, bukan default kolom.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $santri = Santri::query()->findOrFail($data['santri_id']);
        $jenis = JenisPelanggaran::query()->findOrFail($data['jenis_pelanggaran_id']);

        return app(DisiplinService::class)->catat($santri, $jenis, auth()->user(), [
            'waktu_kejadian' => $data['waktu_kejadian'] ?? now(),
            'lokasi' => $data['lokasi'] ?? null,
            'kronologi' => (string) $data['kronologi'],
            'foto_bukti' => $data['foto_bukti'] ?? null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
