<?php

namespace Modules\Akademik\Filament\Resources\SetoranSorogan\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Modules\Akademik\Filament\Resources\SetoranSorogan\SetoranSoroganResource;
use Modules\Akademik\Models\SetoranSorogan;
use Modules\Akademik\Services\SoroganService;
use Modules\Kesantrian\Models\Santri;

/**
 * Issue #330 — Progres baca kitab per santri: rekap per kitab (posisi
 * terakhir, khatam, nilai rata) + riwayat setoran — mirip progres hafalan
 * Tahfidz (cetak progres), tetapi interaktif di panel.
 */
class ProgresSorogan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SetoranSoroganResource::class;

    protected string $view = 'filament.pages.progres-sorogan';

    protected static ?string $title = 'Progres Baca Kitab per Santri';

    public ?int $santri_id = null;

    /** @var array<int, array<string, mixed>> */
    public array $progres = [];

    /** @var array<int, array<string, mixed>> */
    public array $riwayat = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('santri_id')
                ->label('Santri')
                ->options(fn () => Santri::query()->orderBy('nama')->pluck('nama', 'id')->all())
                ->searchable()
                ->live()
                ->afterStateUpdated(fn () => $this->muatProgres()),
        ]);
    }

    public function muatProgres(): void
    {
        $this->progres = [];
        $this->riwayat = [];

        $santri = $this->santri_id !== null ? Santri::query()->find($this->santri_id) : null;

        if (! $santri instanceof Santri) {
            return;
        }

        $this->progres = app(SoroganService::class)->progresPerKitab($santri);

        $this->riwayat = SetoranSorogan::query()
            ->where('santri_id', $santri->id)
            ->with(['kitab', 'ustadz'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (SetoranSorogan $s): array => [
                'tanggal' => $s->tanggal?->format('d M Y'),
                'kitab' => $s->kitab?->nama,
                'bab' => $s->bab,
                'halaman' => $s->halaman_dari !== null
                    ? $s->halaman_dari.($s->halaman_sampai !== null && $s->halaman_sampai !== $s->halaman_dari ? '–'.$s->halaman_sampai : '')
                    : null,
                'metode' => $s->metode,
                'ustadz' => $s->ustadz?->name,
                'nilai_kelancaran' => $s->nilai_kelancaran,
                'nilai_makna' => $s->nilai_makna,
                'nilai_nahwu' => $s->nilai_nahwu,
                'nilai_rata' => $s->nilai_rata,
                'status' => $s->status,
                'catatan' => $s->catatan,
            ])
            ->all();
    }
}
