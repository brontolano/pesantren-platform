<?php

namespace Modules\Kesantrian\Filament\Resources\PelanggaranSantri;

use App\Services\StorageQuotaService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Kesantrian\Filament\Resources\PelanggaranSantri\Pages\CreatePelanggaranSantri;
use Modules\Kesantrian\Filament\Resources\PelanggaranSantri\Pages\ListPelanggaranSantri;
use Modules\Kesantrian\Filament\Resources\PelanggaranSantri\Pages\ViewPelanggaranSantri;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;
use Throwable;
use UnitEnum;

/**
 * WP-09 — Catatan pelanggaran/prestasi: form catat (lewat DisiplinService —
 * status awal ikut saklar verifikasi), aksi Verifikasi (gate: bukan pencatat)
 * + Batalkan (alasan wajib), filter status, infolist kronologi + foto bukti
 * (via /_tenant-storage).
 */
class PelanggaranSantriResource extends Resource
{
    protected static ?string $model = PelanggaranSantri::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static UnitEnum|string|null $navigationGroup = 'Kedisiplinan';

    protected static ?int $navigationSort = 9;

    public const STATUS_OPTIONS = [
        'dicatat' => 'Dicatat',
        'diverifikasi' => 'Diverifikasi',
        'sanksi_dijalankan' => 'Sanksi Dijalankan',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];

    public static function getNavigationLabel(): string
    {
        return 'Catatan '.istilah('takzir');
    }

    public static function getModelLabel(): string
    {
        return 'Catatan '.istilah('takzir');
    }

    public static function getPluralModelLabel(): string
    {
        return 'Catatan '.istilah('takzir');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canAny(['view_kedisiplinan', 'manage_kedisiplinan']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_kedisiplinan', 'manage_kedisiplinan']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_kedisiplinan') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false; // perubahan status HANYA via aksi service (jaga alur verifikasi)
    }

    public static function canDelete(Model $record): bool
    {
        return false; // jejak disiplin tidak dihapus — gunakan Batalkan (alasan wajib)
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Catat Kejadian')->schema([
                Select::make('santri_id')
                    ->label('Santri')
                    ->options(fn () => Santri::query()->where('status', 'aktif')->orderBy('nama')->limit(500)->pluck('nama', 'id'))
                    ->getSearchResultsUsing(fn (string $search) => Santri::query()
                        ->where('nama', 'ilike', "%{$search}%")
                        ->orWhere('nis', 'ilike', "%{$search}%")
                        ->limit(50)->pluck('nama', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('jenis_pelanggaran_id')
                    ->label('Jenis')
                    ->options(fn (): array => JenisPelanggaran::query()
                        ->where('aktif', true)
                        ->when(! app(DisiplinService::class)->poinPositifAktif(), fn ($q) => $q->where('kategori', '!=', 'prestasi'))
                        ->orderBy('kategori')
                        ->get()
                        ->mapWithKeys(fn (JenisPelanggaran $j): array => [$j->id => "{$j->nama} ({$j->kategori}, {$j->poin} poin)"])
                        ->all())
                    ->searchable()
                    ->required(),
                DateTimePicker::make('waktu_kejadian')
                    ->label('Waktu Kejadian')
                    ->default(now())
                    ->required(),
                TextInput::make('lokasi')->maxLength(255)->nullable(),
                Textarea::make('kronologi')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                FileUpload::make('foto_bukti')
                    ->label('Foto Bukti (opsional)')
                    ->image()
                    ->disk(StorageQuotaService::diskName())
                    ->directory('disiplin')
                    ->maxSize(4096)
                    ->nullable()
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kejadian')->schema([
                TextEntry::make('santri.nama')->label('Santri'),
                TextEntry::make('jenis.nama')->label('Jenis'),
                TextEntry::make('jenis.kategori')->label('Kategori')->badge(),
                TextEntry::make('jenis.poin')->label('Poin'),
                TextEntry::make('waktu_kejadian')->dateTime('d M Y H:i'),
                TextEntry::make('lokasi')->placeholder('—'),
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_OPTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextEntry::make('pencatat.name')->label('Pencatat'),
                TextEntry::make('verifikator.name')->label('Verifikator')->placeholder('—'),
                TextEntry::make('verified_at')->label('Diverifikasi pada')->dateTime('d M Y H:i')->placeholder('—'),
                TextEntry::make('alasan_batal')->label('Alasan Pembatalan')->placeholder('—')->columnSpanFull(),
                TextEntry::make('kronologi')->columnSpanFull(),
                // Foto via /_tenant-storage — BUKAN Storage::url (404 di Docker).
                ImageEntry::make('foto_bukti')
                    ->label('Foto Bukti')
                    ->getStateUsing(fn (PelanggaranSantri $record): ?string => $record->fotoBuktiUrl())
                    ->placeholder('Tidak ada foto')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada catatan '.istilah('takzir'))
            ->columns([
                TextColumn::make('waktu_kejadian')->dateTime('d M Y H:i')->sortable()->label('Waktu'),
                TextColumn::make('santri.nama')->label('Santri')->searchable(),
                TextColumn::make('jenis.nama')->label('Jenis')->wrap(),
                TextColumn::make('jenis.kategori')->label('Kategori')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ringan' => 'info', 'sedang' => 'warning', 'berat' => 'danger', 'prestasi' => 'success', default => 'gray',
                    }),
                TextColumn::make('jenis.poin')->label('Poin'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_OPTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('pencatat.name')->label('Pencatat')->toggleable(),
            ])
            ->defaultSort('waktu_kejadian', 'desc')
            ->filters([
                SelectFilter::make('status')->options(self::STATUS_OPTIONS),
                SelectFilter::make('jenis_pelanggaran_id')
                    ->label('Jenis')
                    ->options(fn () => JenisPelanggaran::query()->pluck('nama', 'id')),
            ])
            ->actions(static::rowActions())
            // A5 — menu klik-kanan (leek/filament-right-click): MEMAKAI ULANG
            // action baris yang SAMA (Lihat/Verifikasi/Catat Sanksi/Selesai/
            // Batalkan) — nol logika/izin baru; ->visible() (status + permission
            // manage_kedisiplinan) tetap berlaku di menu klik-kanan.
            ->contextMenuActions(static::rowActions());
    }

    /**
     * Action baris tabel Pelanggaran/Ta'zir — SATU sumber dipakai ulang oleh
     * ->actions() DAN ->contextMenuActions() (menu klik-kanan A5). Ekstraksi
     * verbatim dari blok actions lama; nol logika baru; ->visible()/otorisasi
     * (siklus status + manage_kedisiplinan) tetap berlaku.
     *
     * @return array<int, \Filament\Actions\Action>
     */
    protected static function rowActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('verifikasi')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PelanggaranSantri $record): bool => $record->status === 'dicatat'
                        && (auth()->user()?->can('manage_kedisiplinan') ?? false)
                        && (int) $record->pencatat_id !== (int) auth()->id())
                    ->action(function (PelanggaranSantri $record): void {
                        try {
                            app(DisiplinService::class)->verifikasi($record, auth()->user());
                            Notification::make()->success()->title('Pelanggaran diverifikasi — poin berlaku.')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Gagal verifikasi')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('catatSanksi')
                    ->label('Catat Sanksi')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->visible(fn (PelanggaranSantri $record): bool => in_array($record->status, ['diverifikasi', 'sanksi_dijalankan'], true)
                        && (auth()->user()?->can('manage_kedisiplinan') ?? false))
                    ->schema([
                        TextInput::make('jenis_sanksi')
                            ->label('Jenis Sanksi')
                            ->maxLength(255)
                            ->placeholder('Kosongkan = sanksi default jenis pelanggaran')
                            ->helperText('mis. Pembinaan, membersihkan masjid, panggil wali.'),
                        DatePicker::make('tanggal')->label('Tanggal Pelaksanaan')->default(now()),
                        Textarea::make('catatan')->label('Catatan')->rows(2),
                    ])
                    ->action(function (PelanggaranSantri $record, array $data): void {
                        try {
                            $payload = ['tanggal' => $data['tanggal'] ?? null, 'catatan' => $data['catatan'] ?? null];
                            if (filled($data['jenis_sanksi'] ?? null)) {
                                $payload['jenis_sanksi'] = $data['jenis_sanksi'];
                            }
                            app(DisiplinService::class)->catatSanksi($record, auth()->user(), $payload);
                            Notification::make()->success()->title('Sanksi tercatat — status: Sanksi Dijalankan.')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Gagal mencatat sanksi')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('selesaikan')
                    ->label('Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Tandai kasus disiplin ini selesai (sanksi tuntas / ditutup).')
                    ->visible(fn (PelanggaranSantri $record): bool => in_array($record->status, ['diverifikasi', 'sanksi_dijalankan'], true)
                        && (auth()->user()?->can('manage_kedisiplinan') ?? false))
                    ->action(function (PelanggaranSantri $record): void {
                        try {
                            app(DisiplinService::class)->selesaikan($record);
                            Notification::make()->success()->title('Kasus disiplin ditandai selesai.')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Gagal menyelesaikan')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('batalkan')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PelanggaranSantri $record): bool => ! in_array($record->status, ['selesai', 'dibatalkan'], true)
                        && (auth()->user()?->can('manage_kedisiplinan') ?? false))
                    ->schema([
                        Textarea::make('alasan')->label('Alasan Pembatalan')->required()->rows(3),
                    ])
                    ->action(function (PelanggaranSantri $record, array $data): void {
                        try {
                            app(DisiplinService::class)->batalkan($record, auth()->user(), (string) ($data['alasan'] ?? ''));
                            Notification::make()->success()->title('Catatan dibatalkan — poin dikembalikan.')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Gagal membatalkan')->body($e->getMessage())->send();
                        }
                    }),
        ];
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'dicatat' => 'warning',
            'diverifikasi' => 'info',
            'sanksi_dijalankan' => 'primary',
            'selesai' => 'success',
            'dibatalkan' => 'gray',
            default => 'gray',
        };
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['santri', 'jenis', 'pencatat', 'verifikator']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPelanggaranSantri::route('/'),
            'create' => CreatePelanggaranSantri::route('/create'),
            'view' => ViewPelanggaranSantri::route('/{record}'),
        ];
    }
}
