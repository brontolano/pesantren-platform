<?php

namespace Modules\AI\Filament\Pages;

use App\Services\ModuleService;
use App\Services\StorageQuotaService;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Services\Rag\IngestService;

/**
 * T-A030 — halaman kelola Sumber Pengetahuan RAG (panel TENANT, grup
 * "AI Khadim Digital"). Mengikuti pola halaman T-A011 (PengaturanAi /
 * PersetujuanAi): gating modul `ai` + permission `ai.pengetahuan.kelola`.
 *
 * Unggah file (.txt/.md/.docx; PDF ditolak dgn pesan jelas saat proses)
 * ATAU tempel teks langsung; daftar sumber + aksi re-ingest & hapus tuntas
 * (right to erasure, konfirmasi di blade).
 */
class SumberPengetahuan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Komunikasi & AI';

    protected static ?string $navigationLabel = 'Sumber Pengetahuan';

    protected static ?string $title = 'Sumber Pengetahuan — Khadim Digital';

    protected static ?string $slug = 'sumber-pengetahuan-ai';

    protected static ?int $navigationSort = 3;

    protected string $view = 'ai::filament.pages.sumber-pengetahuan';

    /** State form unggah (statePath). */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'tenant') {
            return false;
        }

        $modulTampil = app(ModuleService::class)
            ->isVisibleForTenant('ai', tenancy()->initialized ? tenant() : null);

        return $modulTampil && (Auth::user()?->can('ai.pengetahuan.kelola') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill(['kategori' => 'lainnya']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Tambah Sumber Pengetahuan')
                    ->description('Unggah dokumen (.txt, .md, .docx) ATAU tempel teks langsung. Dokumen diproses di latar belakang lalu siap dirujuk Khadim Digital.')
                    ->columns(2)
                    ->components([
                        TextInput::make('judul')
                            ->label('Judul sumber')
                            ->required()
                            ->maxLength(200),
                        Select::make('kategori')
                            ->label('Kategori')
                            ->options([
                                'kurikulum' => 'Kurikulum',
                                'sop' => 'SOP',
                                'ppdb' => 'PPDB',
                                'faq' => 'FAQ',
                                'lainnya' => 'Lainnya',
                            ])
                            ->required(),
                        FileUpload::make('berkas')
                            ->label('Berkas dokumen')
                            ->helperText('Format: .txt, .md, .docx. Kosongkan bila menempel teks di bawah.')
                            ->acceptedFileTypes([
                                'text/plain',
                                'text/markdown',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->maxSize(StorageQuotaService::maxFileKb())
                            ->storeFiles(false)
                            ->columnSpanFull(),
                        Textarea::make('teks')
                            ->label('Atau tempel teks langsung')
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Daftar sumber untuk blade (terbaru dulu). */
    public function daftarSumber(): Collection
    {
        return AiKnowledgeSource::query()->orderByDesc('created_at')->get();
    }

    public function simpan(): void
    {
        $this->pastikanBoleh();

        $state = $this->form->getState();
        $berkas = $state['berkas'] ?? null;
        $teks = trim((string) ($state['teks'] ?? ''));

        if ($berkas instanceof TemporaryUploadedFile) {
            $isi = $berkas;
        } elseif ($teks !== '') {
            $isi = $teks;
        } else {
            Notification::make()
                ->title('Belum ada konten')
                ->body('Mohon unggah berkas atau tempel teks terlebih dahulu.')
                ->warning()
                ->send();

            return;
        }

        try {
            app(IngestService::class)->daftarkanSumber(
                (string) $state['judul'],
                (string) $state['kategori'],
                $isi,
                Auth::user(),
            );

            $this->form->fill(['kategori' => 'lainnya']);

            Notification::make()
                ->title('Sumber pengetahuan terdaftar')
                ->body('Dokumen sedang diproses di latar belakang. Status akan berubah menjadi "siap" bila selesai.')
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Sumber belum terdaftar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function prosesUlang(int $sourceId): void
    {
        $this->pastikanBoleh();

        app(IngestService::class)->reIngest($sourceId);

        Notification::make()
            ->title('Re-ingest dijadwalkan')
            ->body('Dokumen akan diproses ulang; potongan lama diganti seluruhnya.')
            ->success()
            ->send();
    }

    public function hapusTuntas(int $sourceId): void
    {
        $this->pastikanBoleh();

        app(IngestService::class)->hapusTuntas($sourceId);

        Notification::make()
            ->title('Sumber dihapus tuntas')
            ->body('Sumber, seluruh potongan pengetahuan, vektor, dan berkas fisiknya telah dihapus permanen.')
            ->success()
            ->send();
    }

    private function pastikanBoleh(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
