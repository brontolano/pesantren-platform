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
use Modules\AI\Models\AiDrafRpp;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Models\AiTemplateRpp;
use Modules\AI\Services\Generator\RppGenerator;

/**
 * T-A041 — halaman Generator RPP / Modul Ajar (panel TENANT, grup
 * "AI Khadim Digital") — KEMBARAN halaman T-A040 (GeneratorSoal): gating
 * modul `ai` + permission `ai.generator.rpp` (asatidz/admin).
 *
 * Form-first (PRD §3, komponen T-A090 <x-ai::panel-asisten>): pilih template +
 * mapel + sumber + isian awal per seksi (opsional) → draf antri + job queue
 * 'ai' (async-by-default) → daftar draf ber-polling → detail: editor konten
 * per seksi + blok sitasi per seksi + Sahkan (human-in-the-loop) + cetak +
 * unduh dokumen Word.
 *
 * Kelola template (CRUD sederhana) digerbangi permission TERPISAH
 * `ai.template.kelola` (Wakakurikulum/admin) — struktur dokumen milik tenant
 * (Aturan Emas #11: tanpa taksonomi regulasi di kode).
 */
class GeneratorRpp extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Khadim';

    protected static ?string $navigationLabel = 'Generator RPP';

    protected static ?string $title = 'Generator RPP / Modul Ajar — Khadim Digital';

    protected static ?string $slug = 'generator-rpp-ai';

    protected static ?int $navigationSort = 6;

    protected string $view = 'ai::filament.pages.generator-rpp';

    /** State form pembuatan draf (statePath). */
    public ?array $data = [];

    /** Isian awal guru per kunci seksi template terpilih (opsional). */
    public array $inputGuru = [];

    /** Draf yang sedang dibuka detailnya (null = hanya daftar). */
    public ?int $drafTerpilihId = null;

    /** Salinan isi seksi yang sedang diedit (index array). */
    public array $isiEdit = [];

    /** Editor template (null = tertutup): {id|null, nama, jenis_lembaga, aktif, seksi[]}. */
    public ?array $templateForm = null;

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'tenant') {
            return false;
        }

        $modulTampil = app(ModuleService::class)
            ->isVisibleForTenant('ai', tenancy()->initialized ? tenant() : null);

        return $modulTampil && (Auth::user()?->can('ai.generator.rpp') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Susun draf RPP / modul ajar baru')
                    ->description('Pilih template milik pesantren Anda — Khadim Digital mengisi tiap seksi dari materi kurikulum yang sudah diunggah di Sumber Pengetahuan, dipandu petunjuk seksi dan catatan awal Anda. Anda yang meninjau dan mengesahkan.')
                    ->columns(2)
                    ->components([
                        Select::make('template_id')
                            ->label('Template RPP')
                            ->options(fn (): array => AiTemplateRpp::query()
                                ->where('aktif', true)
                                ->orderBy('nama')
                                ->pluck('nama', 'id')
                                ->all())
                            ->required()
                            ->live()
                            ->helperText('Struktur seksi dokumen mengikuti template pilihan Anda.'),
                        TextInput::make('mapel')
                            ->label('Mapel / kitab')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('kelas')
                            ->label('Kelas / jenjang (opsional)')
                            ->maxLength(100),
                        Select::make('source_id')
                            ->label('Sumber pengetahuan (opsional)')
                            ->options(fn (): array => AiKnowledgeSource::query()
                                ->where('status', AiKnowledgeSource::STATUS_SIAP)
                                ->where('aktif', true)
                                ->orderBy('judul')
                                ->pluck('judul', 'id')
                                ->all())
                            ->placeholder('Semua materi kategori Kurikulum')
                            ->helperText('Pilih satu dokumen bila RPP harus bersumber dari materi tertentu.'),
                    ]),
            ]);
    }

    // ------------------------------------------------------------------
    // Data untuk blade
    // ------------------------------------------------------------------

    /** Seksi template yang dipilih di form (untuk isian awal per seksi). */
    public function seksiTemplateTerpilih(): array
    {
        $templateId = $this->data['template_id'] ?? null;

        if ($templateId === null || $templateId === '') {
            return [];
        }

        $template = AiTemplateRpp::query()->find((int) $templateId);

        return $template !== null ? array_values((array) $template->seksi) : [];
    }

    /** Daftar draf (terbaru dulu) — blade memasang wire:poll untuk status. */
    public function daftarDraf(): Collection
    {
        return AiDrafRpp::query()->orderByDesc('created_at')->limit(50)->get();
    }

    public function drafTerpilih(): ?AiDrafRpp
    {
        return $this->drafTerpilihId !== null
            ? AiDrafRpp::query()->find($this->drafTerpilihId)
            : null;
    }

    public function daftarTemplate(): Collection
    {
        return AiTemplateRpp::query()->orderBy('nama')->get();
    }

    public function bolehKelolaTemplate(): bool
    {
        return Auth::user()?->can('ai.template.kelola') ?? false;
    }

    // ------------------------------------------------------------------
    // Aksi draf
    // ------------------------------------------------------------------

    public function buatDraf(): void
    {
        $this->pastikanBoleh();

        try {
            $draf = app(RppGenerator::class)->buatDraf(
                $this->form->getState() + ['input_guru' => $this->inputGuru],
                Auth::user(),
            );

            $this->form->fill([]);
            $this->inputGuru = [];
            $this->drafTerpilihId = (int) $draf->getKey();
            $this->isiEdit = [];

            Notification::make()
                ->title('Draf RPP sedang disusun')
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

        $draf = AiDrafRpp::query()->find($id);

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
            $draf = app(RppGenerator::class)->perbaruiIsi($draf, $this->isiEdit);
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
            $draf = app(RppGenerator::class)->sahkan($draf, Auth::user());
            $this->muatIsiEdit($draf);

            Notification::make()
                ->title('Dokumen RPP disahkan')
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

        AiDrafRpp::query()->whereKey($id)->first()?->delete();

        if ($this->drafTerpilihId === $id) {
            $this->tutupDetail();
        }

        Notification::make()
            ->title('Draf dihapus')
            ->success()
            ->send();
    }

    // ------------------------------------------------------------------
    // Aksi template (permission terpisah ai.template.kelola)
    // ------------------------------------------------------------------

    public function bukaTemplateBaru(): void
    {
        $this->pastikanBolehTemplate();

        $this->templateForm = [
            'id' => null,
            'nama' => '',
            'jenis_lembaga' => '',
            'aktif' => true,
            'seksi' => [
                ['kunci' => '', 'judul' => '', 'petunjuk' => '', 'wajib' => true],
            ],
        ];
    }

    public function editTemplate(int $id): void
    {
        $this->pastikanBolehTemplate();

        $template = AiTemplateRpp::query()->find($id);

        if ($template === null) {
            return;
        }

        $this->templateForm = [
            'id' => (int) $template->getKey(),
            'nama' => (string) $template->nama,
            'jenis_lembaga' => (string) ($template->jenis_lembaga ?? ''),
            'aktif' => (bool) $template->aktif,
            'seksi' => array_values((array) $template->seksi),
        ];
    }

    public function tambahSeksiTemplate(): void
    {
        $this->pastikanBolehTemplate();

        if ($this->templateForm === null) {
            return;
        }

        $this->templateForm['seksi'][] = ['kunci' => '', 'judul' => '', 'petunjuk' => '', 'wajib' => false];
    }

    public function hapusSeksiTemplate(int $index): void
    {
        $this->pastikanBolehTemplate();

        if ($this->templateForm === null) {
            return;
        }

        unset($this->templateForm['seksi'][$index]);
        $this->templateForm['seksi'] = array_values($this->templateForm['seksi']);
    }

    public function simpanTemplate(): void
    {
        $this->pastikanBolehTemplate();

        if ($this->templateForm === null) {
            return;
        }

        try {
            $template = isset($this->templateForm['id']) && $this->templateForm['id'] !== null
                ? AiTemplateRpp::query()->find((int) $this->templateForm['id'])
                : null;

            app(RppGenerator::class)->simpanTemplate($this->templateForm, Auth::user(), $template);

            $this->templateForm = null;

            Notification::make()
                ->title('Template tersimpan')
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Template belum tersimpan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function batalTemplate(): void
    {
        $this->templateForm = null;
    }

    public function hapusTemplate(int $id): void
    {
        $this->pastikanBolehTemplate();

        $template = AiTemplateRpp::query()->find($id);

        if ($template === null) {
            return;
        }

        try {
            app(RppGenerator::class)->hapusTemplate($template);

            Notification::make()
                ->title('Template dihapus')
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Template tidak dapat dihapus')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    /** Muat salinan isi yang bisa diedit (sitasi ikut demi tampilan, tapi server mengabaikannya saat simpan). */
    private function muatIsiEdit(AiDrafRpp $draf): void
    {
        $this->isiEdit = array_values((array) $draf->isi);
    }

    private function pastikanBoleh(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    private function pastikanBolehTemplate(): void
    {
        abort_unless(static::canAccess() && $this->bolehKelolaTemplate(), 403);
    }
}
