<?php

namespace App\Filament\Portal\Widgets;

use Filament\Widgets\Widget;
use Modules\PPDB\Models\CalonSantri;

/**
 * Ringkasan status pendaftaran milik peserta yang sedang login (beranda portal).
 * Hanya membaca record milik sendiri (where user_id = auth id) + scope tenant.
 */
class PesertaStatusWidget extends Widget
{
    protected string $view = 'filament.portal.widgets.peserta-status';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'calon' => CalonSantri::query()->where('user_id', auth()->id())->first(),
        ];
    }
}
