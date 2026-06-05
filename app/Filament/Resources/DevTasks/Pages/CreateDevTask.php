<?php

namespace App\Filament\Resources\DevTasks\Pages;

use App\Filament\Resources\DevTasks\DevTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDevTask extends CreateRecord
{
    protected static string $resource = DevTaskResource::class;
}
