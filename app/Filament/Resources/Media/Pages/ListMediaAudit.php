<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListMediaAudit extends ListRecords
{
    protected static string $resource = MediaAuditResource::class;
}
