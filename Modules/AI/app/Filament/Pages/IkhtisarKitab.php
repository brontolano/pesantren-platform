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
use Modules\AI\Models\AiDrafIkhtisar;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Services\Generator\IkhtisarGenerator;

/**
 * T-A042 — halaman Ikhtisar Materi Kitab & (opt-in) Kerangka Kultum (panel
 * TENANT, grup "AI Khadim Digital") — KEMBARAN halaman T-A040/T-A041:
 * gating modul `ai` + permission `ai.generator.ikhtisar` (asatidz/admin).
 *
 * Form-first (PRD §3, komponen T-A090 <x-ai::panel-asisten>): pilih jenis +
 * sumber + bab/tema → draf antri + job queue 'ai' (async-by-default) →
 * daftar draf ber-polling → detail: editor isi + sitasi (terkunci) + Sahkan
 * (human-in-the-loop) + handout cetak print-friendly.
 *
 * Opsi jenis "kultum" HANYA tampil bila fitur generator_kultum aktif di
 * pengaturan tenant (default OFF — opt-in, rekomendasi Q8 PRD); penegakan
 * keras tetap di IkhtisarGenerator::buatDraf + PolicyEngine.
 */
class IkhtisarKitab extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Khadim';

    protected static ?string $navigationLabel = 'Ikhtisar Kitab';

    protected static ?string $title = 'Ikhtisar Materi Kitab — Khadim Digital';

    protected static ?string $slug = 'ikhtisar-kitab-ai';

    protected static ?int $navigationSort = 7;

    protected string $view = 'ai::filament.pages.ikhtisar-kitab';

    /** State form pembuatan draf (statePath). */
    public ?array $data = [];

    /** Draf yang sedang dibuka detailnya (null = hanya daftar). */
    public ?int $drafTerpilihId = null;

    /** Salinan isi yang sedang diedit (struktur per jenis — lihat model). */
    public array $isiEdit = [];

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'tenant') {
            return false;
        }

        $modulTampil = app(ModuleService::class)
            ->isVisibleForTenant('ai', tenancy()->initialized ? tenant() : null);

        return $modulTampil && (Auth::user()?->can('ai.generator.ikhtisar') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill(['jenis' => AiDrafIkhtisar::JENIS_IKHTISAR]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Susun dokumen baru')
                    ->description('Khadim Digital merangkum materi kitab yang sudah diunggah di Sumber Pengetahuan menjadi poin pembelajaran, istilah kunci, dan pertanyaan diskusi — lengkap dengan sumber rujukan. Anda yang meninjau dan mengesahkan.')
                    ->columns(2)
                    ->components([
                        Select::make('jenis')
                            ->label('Jenis dokumen')
                            ->options(fn (): array => $this->opsiJenis())
                            ->required()
                            ->live()
                            ->helperText('Pilihan jenis mengikuti fitur yang diaktifkan pesantren Anda di Pengaturan AI.'),
                        Select::make('source_id')
                            ->label(fn (): string => $this->jenisTerpilih() === AiDrafIkhtisar::JENIS_KULTUM
                                ? 'Sumber pengetahuan (opsional)'
                                : 'Kitab / materi (Sumber Pengetahuan)')
                            ->options(fn (): array => AiKnowledgeSource::query()
                                ->where('status', AiKnowledgeSource::STATUS_SIAP)
                                ->where('aktif', true)
                                ->orderBy('judul')
                                ->pluck('judul', 'id')
                                ->all())
                            ->required(fn (): bool => $this->jenisTerpilih() === AiDrafIkhtisar::JENIS_IKHTISAR)
                            ->placeholder(fn (): string => $this->jenisTerpilih() === AiDrafIkhtisar::JENIS_KULTUM
                                ? 'Seluruh materi pesantren'
                                : 'Pilih kitab/materi')
                            ->helperText('Materi yang sudah diunggah pesantren Anda di menu Sumber Pengetahuan.'),
                        TextInput::make('bab')
                            ->label('Bab / bagian (opsional)')
                            ->maxLength(150)
                            ->visible(fn (): bool => $this->jenisTerpilih() === AiDrafIkhtisar::JENIS_IKHTISAR),
                        TextInput::make('tema')
                            ->label('Tema')
                            ->maxLength(150)
                            ->visible(fn (): bool => $this->jenisTerpilih() === AiDrafIkhtisar::JENIS_KULTUM)
                            ->required(fn (): bool => $this->jenisTerpilih() === AiDrafIkhtisar::JENIS_KULTUM),
                        TextInput::make('durasi_menit')
                            ->label('Durasi (menit, opsional)')
                            ->numeric()
                            ->minValue((int) config('ai.generator.ikhtisar.durasi_min', 3))
                            ->maxValue((int) config('ai.generator.ikhtisar.durasi_max', 60))
                            ->visible(fn (): bool => $this->jenisTerpilih() === AiDrafIkhtisar::JENIS_KULTUM),
                    ]),
            ]);
    }

    // ------------------------------------------------------------------
    // Data untuk blade
    // ------------------------------------------------------------------

    /**
     * Opsi jenis dokumen: "kultum" hanya muncul bila fitur generator_kultum
     * AKTIF di pengaturan tenant (opt-in, default OFF). AC T-A042: tenant
     * dengan kultum OFF tidak pernah melihat opsinya.
     *
     * @return array<string, string>
     */
    public function opsiJenis(): array
    {
        $opsi = [AiDrafIkhtisar::JENIS_IKHTISAR => 'Ikhtisar materi kitab'];

        if (IkhtisarGenerator::kultumAktif()) {
            $opsi[AiDrafIkhtisar::JENIS_KULTUM] = 'Kerangka kultum (dalil dilengkapi Ustadz)';
        }

        return $opsi;
    }

    public function jenisTerpilih(): string
    {
        $jenis = (string) ($this->data['jenis'] ?? AiDrafIkhtisar::JENIS_IKHTISAR);

        return array_key_exists($jenis, $this->opsiJenis()) ? $jenis : AiDrafIkhtisar::JENIS_IKHTISAR;
    }

    /** Daftar draf (terbaru dulu) — blade memasang wire:poll untuk status. */
    public function daftarDraf(): Collection
    {
        return AiDrafIkhtisar::query()->orderByDesc('created_at')->limit(50)->get();
    }

    public function drafTerpilih(): ?AiDrafIkhtisar
    {
        return $this->drafTerpilihId !== null
            ? AiDrafIkhtisar::query()->find($this->drafTerpilihId)
            : null;
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    public function buatDraf(): void
    {
        $this->pastikanBoleh();

        try {
            $draf = app(IkhtisarGenerator::class)->buatDraf($this->form->getState(), Auth::user());

            $this->form->fill(['jenis' => AiDrafIkhtisar::JENIS_IKHTISAR]);
            $this->drafTerpilihId = (int) $draf->getKey();
            $this->isiEdit = [];

            Notification::make()
                ->title('Draf sedang disusun')
                ->body('Dokumen "'.$draf->judul.'" masuk antrean. Anda akan menerima notifikasi saat draf siap ditinjau.')
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

        $draf = AiDrafIkhtisar::query()->find($id);

        if ($draf === null) {
            return;
        }

        $this->drafTerpilihId = (int) $draf->getKey();
        $this->muatIsiEdit($draf);
    }

    public function tutupDetail(): void
    {
        $this->drafTerpilihId = null;
        $this->isiEdit = [];
    }

    public function simpanIsi(): void
    {
        $this->pastikanBoleh();

        $draf = $this->drafTerpilih();

        if ($draf === null) {
            return;
        }

        try {
            $draf = app(IkhtisarGenerator::class)->perbaruiIsi($draf, $this->isiEdit);
            $this->muatIsiEdit($draf);

            Notification::make()
                ->title('Perubahan isi tersimpan')
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
            $draf = app(IkhtisarGenerator::class)->sahkan($draf, Auth::user());
            $this->muatIsiEdit($draf);

            Notification::make()
                ->title('Dokumen disahkan')
                ->body('Dokumen "'.$draf->judul.'" kini berstatus disahkan atas nama Anda. Jazakumullahu khairan.')
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

        AiDrafIkhtisar::query()->whereKey($id)->first()?->delete();

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

    /** Muat salinan isi yang bisa diedit (sitasi_idx/disclaimer ikut demi tampilan; server mengabaikannya saat simpan). */
    private function muatIsiEdit(AiDrafIkhtisar $draf): void
    {
        $this->isiEdit = (array) $draf->isi;
    }

    private function pastikanBoleh(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
