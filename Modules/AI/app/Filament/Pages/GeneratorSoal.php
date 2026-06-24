<?php

namespace Modules\AI\Filament\Pages;

use App\Services\ModuleService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\AI\Models\AiDrafSoal;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Services\Generator\SoalGenerator;

/**
 * T-A040 — halaman Generator Soal & Kisi-kisi (panel TENANT, grup
 * "AI Khadim Digital"). Pola halaman T-A030 (SumberPengetahuan): gating
 * modul `ai` + permission `ai.generator.soal` (asatidz/admin).
 *
 * Form-first (PRD §3, komponen T-A090 <x-ai::panel-asisten>): formulir →
 * draf antri + job queue 'ai' (async-by-default) → daftar draf ber-polling →
 * detail: editor butir + blok sitasi per butir + Sahkan (human-in-the-loop)
 * + cetak. Ekspor ke modul Ujian: NONAKTIF — belum ada service tulis resmi
 * bank soal (lihat TODO di blade; Aturan Emas #2: dilarang insert langsung
 * ke tabel modul lain).
 */
class GeneratorSoal extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Khadim';

    protected static ?string $navigationLabel = 'Generator Soal';

    protected static ?string $title = 'Generator Soal & Kisi-kisi — Khadim Digital';

    protected static ?string $slug = 'generator-soal-ai';

    protected static ?int $navigationSort = 5;

    protected string $view = 'ai::filament.pages.generator-soal';

    /** State form pembuatan draf (statePath). */
    public ?array $data = [];

    /** Draf yang sedang dibuka detailnya (null = hanya daftar). */
    public ?int $drafTerpilihId = null;

    /** Salinan butir yang sedang diedit (index array, bukan keyed). */
    public array $butirEdit = [];

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'tenant') {
            return false;
        }

        $modulTampil = app(ModuleService::class)
            ->isVisibleForTenant('ai', tenancy()->initialized ? tenant() : null);

        return $modulTampil && (Auth::user()?->can('ai.generator.soal') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'jumlah' => (int) config('ai.generator.soal.default_jumlah', 10),
            'tipe' => 'pilihan_ganda',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $maks = app(SoalGenerator::class)->jumlahTerjepit(PHP_INT_MAX);

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Susun paket soal baru')
                    ->description('Isi formulir berikut — Khadim Digital menyusun draf soal + kunci + kisi-kisi dari materi kurikulum yang sudah diunggah di Sumber Pengetahuan. Anda yang meninjau dan mengesahkan.')
                    ->columns(2)
                    ->components([
                        TextInput::make('mapel')
                            ->label('Mapel / kitab')
                            ->required()
                            ->maxLength(150),
                        Select::make('source_id')
                            ->label('Sumber pengetahuan (opsional)')
                            ->options(fn (): array => AiKnowledgeSource::query()
                                ->where('status', AiKnowledgeSource::STATUS_SIAP)
                                ->where('aktif', true)
                                ->orderBy('judul')
                                ->pluck('judul', 'id')
                                ->all())
                            ->placeholder('Semua materi kategori Kurikulum')
                            ->helperText('Pilih satu dokumen bila soal harus bersumber dari materi tertentu.'),
                        TextInput::make('bab')
                            ->label('Bab / topik (opsional)')
                            ->maxLength(150),
                        TextInput::make('jenjang')
                            ->label('Jenjang / kelas (opsional)')
                            ->maxLength(64),
                        TextInput::make('jumlah')
                            ->label('Jumlah butir')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue($maks)
                            ->helperText("Maksimal {$maks} butir per paket.")
                            ->required(),
                        Select::make('tipe')
                            ->label('Tipe soal')
                            ->options([
                                'pilihan_ganda' => 'Pilihan ganda',
                                'esai' => 'Esai',
                                'campuran' => 'Campuran',
                            ])
                            ->required(),
                        Select::make('tingkat_kognitif')
                            ->label('Tingkat kognitif (opsional)')
                            ->options([
                                'C1' => 'C1 — Mengingat',
                                'C2' => 'C2 — Memahami',
                                'C3' => 'C3 — Menerapkan',
                                'C4' => 'C4 — Menganalisis',
                                'C5' => 'C5 — Mengevaluasi',
                                'C6' => 'C6 — Mencipta',
                            ])
                            ->placeholder('Tidak ditentukan'),
                    ]),
            ]);
    }

    // ------------------------------------------------------------------
    // Data untuk blade
    // ------------------------------------------------------------------

    /** Daftar draf (terbaru dulu) — blade memasang wire:poll untuk status. */
    public function daftarDraf(): Collection
    {
        return AiDrafSoal::query()->orderByDesc('created_at')->limit(50)->get();
    }

    public function drafTerpilih(): ?AiDrafSoal
    {
        return $this->drafTerpilihId !== null
            ? AiDrafSoal::query()->find($this->drafTerpilihId)
            : null;
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    public function buatDraf(): void
    {
        $this->pastikanBoleh();

        try {
            $draf = app(SoalGenerator::class)->buatDraf($this->form->getState(), Auth::user());

            $this->form->fill([
                'jumlah' => (int) config('ai.generator.soal.default_jumlah', 10),
                'tipe' => 'pilihan_ganda',
            ]);
            $this->drafTerpilihId = (int) $draf->getKey();
            $this->butirEdit = [];

            Notification::make()
                ->title('Draf soal sedang disusun')
                ->body('Paket "'.$draf->judul.'" masuk antrean. Anda akan menerima notifikasi saat draf siap ditinjau.')
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Draf belum dibuat')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function pilihDraf(int $id): void
    {
        $this->pastikanBoleh();

        $draf = AiDrafSoal::query()->find($id);

        if ($draf === null) {
            return;
        }

        $this->drafTerpilihId = (int) $draf->getKey();
        $this->muatButirEdit($draf);
    }

    public function tutupDetail(): void
    {
        $this->drafTerpilihId = null;
        $this->butirEdit = [];
    }

    public function simpanButir(): void
    {
        $this->pastikanBoleh();

        $draf = $this->drafTerpilih();

        if ($draf === null) {
            return;
        }

        try {
            $draf = app(SoalGenerator::class)->perbaruiButir($draf, $this->butirEdit);
            $this->muatButirEdit($draf);

            Notification::make()
                ->title('Perubahan butir tersimpan')
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Perubahan belum tersimpan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function sahkan(): void
    {
        $this->pastikanBoleh();

        $draf = $this->drafTerpilih();

        if ($draf === null) {
            return;
        }

        try {
            $draf = app(SoalGenerator::class)->sahkan($draf, Auth::user());
            $this->muatButirEdit($draf);

            Notification::make()
                ->title('Paket soal disahkan')
                ->body('Paket "'.$draf->judul.'" kini berstatus disahkan atas nama Anda. Jazakumullahu khairan.')
                ->success()
                ->send();
        } catch (AuthorizationException|\InvalidArgumentException $e) {
            Notification::make()
                ->title('Pengesahan belum terlaksana')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function hapusDraf(int $id): void
    {
        $this->pastikanBoleh();

        AiDrafSoal::query()->whereKey($id)->first()?->delete();

        if ($this->drafTerpilihId === $id) {
            $this->tutupDetail();
        }

        Notification::make()
            ->title('Draf dihapus')
            ->success()
            ->send();
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    /** Muat salinan butir yang bisa diedit (sitasi ikut demi tampilan, tapi server mengabaikannya saat simpan). */
    private function muatButirEdit(AiDrafSoal $draf): void
    {
        $this->butirEdit = array_values((array) $draf->butir);
    }

    private function pastikanBoleh(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
