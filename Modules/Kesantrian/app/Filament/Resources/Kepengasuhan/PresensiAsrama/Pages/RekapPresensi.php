<?php

namespace Modules\Kesantrian\Filament\Resources\Kepengasuhan\PresensiAsrama\Pages;

use Modules\Kesantrian\Filament\Resources\Kepengasuhan\PresensiAsrama\PresensiAsramaResource;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Kesantrian\Services\PresensiAsramaService;

class RekapPresensi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = PresensiAsramaResource::class;

    protected string $view = 'filament.pages.rekap-presensi-asrama';

    protected static ?string $title = 'Rekap Presensi Asrama';

    public ?string $dari = null;

    public ?string $sampai = null;

    public ?string $slot = null;

    public array $rekap = [];

    public function mount(): void
    {
        $this->dari = now()->startOfWeek()->toDateString();
        $this->sampai = now()->toDateString();
    }

    public function form(Form $form): Form
    {
        $svc = app(PresensiAsramaService::class);
        $slotOptions = collect($svc->slotAktif())->pluck('label', 'kode')->all();

        return $form->schema([
            DatePicker::make('dari')->default(now()->startOfWeek()),
            DatePicker::make('sampai')->default(now()),
            Select::make('slot')
                ->options($slotOptions)
                ->placeholder('Semua Slot'),
        ]);
    }

    public function lihatRekap(): void
    {
        $query = PresensiAsrama::query()
            ->whereBetween('tanggal', [$this->dari, $this->sampai])
            ->whereNotNull('kamar_id')
            ->with('kamar');

        if ($this->slot) {
            $query->where('slot', $this->slot);
        }

        $rows = $query->get();

        $this->rekap = $rows->groupBy(fn ($r) => $r->kamar?->nomor_kamar ?? 'Tanpa Kamar')
            ->map(fn ($group, $kamar) => [
                'kamar' => $kamar,
                'hadir' => $group->where('status', 'hadir')->count(),
                'alpha' => $group->where('status', 'alpha')->count(),
                'izin' => $group->where('status', 'izin')->count(),
                'sakit' => $group->where('status', 'sakit')->count(),
                'terlambat' => $group->where('status', 'terlambat')->count(),
                'total' => $group->count(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }
}
