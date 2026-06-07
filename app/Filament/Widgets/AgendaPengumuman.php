<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Modules\Kegiatan\Models\Kegiatan;

/**
 * Panel kanan dashboard (mockup): kartu "Pengumuman Terbaru" + "Agenda Mendatang".
 * Pengumuman = state kosong (modul pengumuman terpisah belum ada). Agenda =
 * Kegiatan mendatang (mulai >= hari ini) dari data nyata.
 */
class AgendaPengumuman extends Widget
{
    protected string $view = 'filament.widgets.agenda-pengumuman';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 2;

    protected function getViewData(): array
    {
        $agenda = collect();

        if (class_exists(Kegiatan::class)) {
            $agenda = Kegiatan::query()
                ->whereNotNull('mulai')
                ->where('mulai', '>=', now()->startOfDay())
                ->orderBy('mulai')
                ->limit(5)
                ->get(['nama', 'mulai', 'lokasi']);
        }

        return [
            'agenda' => $agenda,
        ];
    }
}
