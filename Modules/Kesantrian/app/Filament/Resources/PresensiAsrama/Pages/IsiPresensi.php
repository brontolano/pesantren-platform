<?php

namespace Modules\Kesantrian\Filament\Resources\PresensiAsrama\Pages;

use Modules\Kesantrian\Filament\Resources\PresensiAsrama\PresensiAsramaResource;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\Rules\In;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\PresensiAsramaService;
use Modules\Tahfidz\Models\Halaqah;

class IsiPresensi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = PresensiAsramaResource::class;

    protected string $view = 'filament.pages.isi-presensi-asrama';

    protected static ?string $title = 'Isi Presensi Asrama';

    public ?string $mode = 'kamar';

    public ?int $kamar_id = null;

    public ?int $halaqah_id = null;

    public ?string $tanggal = null;

    public ?string $slot = null;

    public array $dataSantri = [];

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
    }

    public function form(Form $form): Form
    {
        $svc = app(PresensiAsramaService::class);
        $slotOptions = collect($svc->slotAktif())->pluck('label', 'kode')->all();

        return $form->schema([
            Select::make('mode')
                ->options(['kamar' => 'Per Kamar', 'halaqah' => 'Per Halaqah'])
                ->default('kamar')
                ->live()
                ->afterStateUpdated(fn () => $this->dataSantri = []),
            Select::make('kamar_id')
                ->label('Kamar')
                ->options(fn () => KamarAsrama::query()->where('is_active', true)->pluck('nomor_kamar', 'id')->all())
                ->searchable()
                ->visible(fn ($get) => $get('mode') === 'kamar')
                ->live()
                ->afterStateUpdated(fn () => $this->loadSantri()),
            Select::make('halaqah_id')
                ->label('Halaqah')
                ->options(fn () => Halaqah::query()->where('aktif', true)->pluck('nama', 'id')->all())
                ->searchable()
                ->visible(fn ($get) => $get('mode') === 'halaqah')
                ->live()
                ->afterStateUpdated(fn () => $this->loadSantri()),
            DatePicker::make('tanggal')
                ->default(now())
                ->required(),
            Select::make('slot')
                ->options($slotOptions)
                ->rules([
                    'required',
                    new In(array_keys($slotOptions)),
                ]),
        ]);
    }

    public function loadSantri(): void
    {
        $svc = app(PresensiAsramaService::class);
        $defaultHadir = $svc->defaultHadir();

        $santriList = collect();

        if ($this->mode === 'kamar' && $this->kamar_id) {
            $kamar = KamarAsrama::find($this->kamar_id);
            if ($kamar) {
                $santriList = $kamar->penempatanAktif()
                    ->with('santri')
                    ->get()
                    ->pluck('santri')
                    ->filter();
            }
        } elseif ($this->mode === 'halaqah' && $this->halaqah_id) {
            $halaqah = Halaqah::find($this->halaqah_id);
            if ($halaqah) {
                $santriList = $halaqah->santriAktif;
            }
        }

        $this->dataSantri = $santriList->map(fn (Santri $s) => [
            'santri_id' => $s->id,
            'nama' => $s->nama,
            'status' => $defaultHadir ? 'hadir' : '',
            'keterangan' => '',
        ])->values()->all();
    }

    public function simpan(): void
    {
        $svc = app(PresensiAsramaService::class);

        if (empty($this->slot)) {
            Notification::make()->title('Pilih slot terlebih dahulu.')->danger()->send();

            return;
        }

        if (empty($this->dataSantri)) {
            Notification::make()->title('Tidak ada santri untuk dipresensi.')->danger()->send();

            return;
        }

        $filtered = array_filter($this->dataSantri, fn ($d) => ! empty($d['status']));

        if (empty($filtered)) {
            Notification::make()->title('Isi status presensi minimal 1 santri.')->danger()->send();

            return;
        }

        try {
            $count = $svc->simpanPresensi(
                kamar: $this->kamar_id ? KamarAsrama::find($this->kamar_id) : null,
                halaqah: $this->halaqah_id ? Halaqah::find($this->halaqah_id) : null,
                tanggal: Carbon::parse($this->tanggal),
                slot: $this->slot,
                dataSantri: $filtered,
                pencatat: auth()->user(),
            );

            Notification::make()
                ->title("Presensi berhasil disimpan ({$count} santri).")
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
