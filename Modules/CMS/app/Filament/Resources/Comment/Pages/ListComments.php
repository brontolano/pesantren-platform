<?php

namespace Modules\CMS\Filament\Resources\Comment\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\CMS\Filament\Resources\Comment\CommentResource;

class ListComments extends ListRecords
{
    protected static string $resource = CommentResource::class;

    protected function getHeaderActions(): array
    {
        // Tidak ada "create" — komentar lahir dari halaman artikel publik.
        return [];
    }
}
