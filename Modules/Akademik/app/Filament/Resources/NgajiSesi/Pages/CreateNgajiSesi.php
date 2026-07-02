<?php

namespace Modules\Akademik\Filament\Resources\NgajiSesi\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Akademik\Filament\Resources\NgajiSesi\NgajiSesiResource;

class CreateNgajiSesi extends CreateRecord
{
    protected static string $resource = NgajiSesiResource::class;

    /** Setelah buat sesi, langsung ke halaman edit utk mengisi peserta. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
