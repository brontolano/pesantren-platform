<?php

namespace App\Filament\Resources\Payroll\Kontrak;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Payroll\Kontrak\Pages\CreateKontrakKerja;
use App\Filament\Resources\Payroll\Kontrak\Pages\EditKontrakKerja;
use App\Filament\Resources\Payroll\Kontrak\Pages\ListKontrakKerja;
use App\Services\StorageQuotaService;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\KontrakKerja;
use Modules\Payroll\Services\KontrakService;

/**
 * WP-16 — Kontrak, SK & Pengabdian.
 *
 * - Upload arsip: acceptedFileTypes WAJIB pdf/jpg/png (pelajaran WP-08
 *   stored-XSS — jangan pernah longgar).
 * - Aksi "Perpanjang": baris BARU bertaut parent_id; baris lama status
 *   'diperpanjang', arsip lama UTUH (tidak menimpa).
 * - Laporan angkatan pengabdian: grouping tabel by periode_khidmat.
 * - Purna pengabdian: isi tanggal_purna → hint "buat penugasan baru via
 *   WP-03" (TIDAK auto-create penugasan).
 * - File diserve via /_tenant-storage (bukan Storage::url — 404 di Docker).
 */
class KontrakKerjaResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = KontrakKerja::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Kontrak & SK';

    protected static ?string $modelLabel = 'Kontrak / SK';

    protected static ?string $pluralModelLabel = 'Kontrak & SK';

    protected static string|\UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 26;

    /** Tipe file arsip yang diizinkan — JANGAN dilonggarkan (WP-08). */
    public const ACCEPTED_FILE_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('payroll') && (auth()->user()?->can('run_payroll') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canEdit(Model $record): bool
    {
        // Arsip historis dikunci — hanya kontrak aktif yang boleh dikoreksi.
        return $record->status === 'aktif' && (auth()->user()?->can('run_payroll') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dokumen Kontrak / SK')->schema([
                Select::make('employee_id')->label('Pegawai')->required()->searchable()
                    ->options(fn () => Employee::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')),
                Select::make('jenis')->label('Jenis')->required()->native(false)
                    ->options(KontrakKerja::JENIS)->live(),
                TextInput::make('nomor')->label('Nomor Dokumen')->required()->maxLength(255),
                // Status dikelola via aksi Perpanjang/Purna — edit bebas
                // merusak semantik rantai (diperpanjang tanpa anak, dst).
                Select::make('status')->label('Status')->native(false)->default('aktif')
                    ->options(KontrakKerja::STATUS)
                    ->disabled()
                    ->dehydrated(fn (?KontrakKerja $record): bool => $record === null),
                DatePicker::make('mulai')->label('Mulai Berlaku')->required(),
                DatePicker::make('selesai')->label('Selesai Berlaku')
                    ->afterOrEqual('mulai')
                    ->helperText('Kosongkan bila tanpa batas waktu (mis. SK GTY).'),
                FileUpload::make('file')
                    ->label('Arsip Dokumen (PDF/JPG/PNG)')
                    ->disk(StorageQuotaService::diskName())
                    ->directory('kontrak-kerja')
                    ->acceptedFileTypes(self::ACCEPTED_FILE_TYPES) // WAJIB — WP-08
                    ->maxSize(5120)
                    ->helperText('Maks 5 MB. Arsip lama tidak pernah ditimpa saat perpanjangan.')
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make('Khusus Pengabdian / DPK')->schema([
                TextInput::make('periode_khidmat')->label('Periode Khidmat / Angkatan')
                    ->placeholder('mis. 2026/2027')->maxLength(255),
                TextInput::make('lembaga_pengirim')->label('Lembaga Pengirim')->maxLength(255),
                DatePicker::make('tanggal_purna')->label('Tanggal Purna')
                    ->helperText('Diisi saat purna pengabdian. Setelah purna, buat penugasan baru via menu Penugasan Pegawai (WP-03).'),
            ])->columns(2)->collapsed(fn (?KontrakKerja $record): bool => ! in_array($record?->jenis, ['sk_pengabdian', 'sk_dpk'], true)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.nama')->label('Pegawai')->searchable()->sortable(),
                TextColumn::make('jenis')->label('Jenis')->badge()
                    ->formatStateUsing(fn (string $state): string => KontrakKerja::JENIS[$state] ?? $state),
                TextColumn::make('nomor')->label('Nomor')->searchable(),
                TextColumn::make('mulai')->label('Mulai')->date('d M Y')->sortable(),
                TextColumn::make('selesai')->label('Selesai')->date('d M Y')->sortable()->placeholder('—'),
                TextColumn::make('periode_khidmat')->label('Angkatan')->placeholder('—')->toggleable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'aktif' => 'success', 'diperpanjang' => 'info', default => 'gray',
                    }),
                TextColumn::make('file')->label('Arsip')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Lihat' : '—')
                    // POLA: serve via /_tenant-storage (Storage::url 404 di Docker)
                    ->url(fn (KontrakKerja $r): ?string => $r->file ? url('/_tenant-storage/'.ltrim($r->file, '/')) : null, true),
            ])
            ->defaultSort('created_at', 'desc')
            ->groups([
                Group::make('periode_khidmat')->label('Angkatan Pengabdian')->collapsible(),
                Group::make('jenis')->label('Jenis'),
            ])
            ->filters([
                SelectFilter::make('jenis')->options(KontrakKerja::JENIS),
                SelectFilter::make('status')->options(KontrakKerja::STATUS),
            ])
            ->actions([
                EditAction::make(),
                Action::make('perpanjang')->label('Perpanjang')->icon('heroicon-o-arrow-path')->color('warning')
                    ->visible(fn (KontrakKerja $r): bool => $r->status === 'aktif' && (auth()->user()?->can('run_payroll') ?? false))
                    ->schema([
                        TextInput::make('nomor')->label('Nomor Dokumen Baru')->required()->maxLength(255),
                        DatePicker::make('mulai')->label('Mulai Berlaku')->required(),
                        DatePicker::make('selesai')->label('Selesai Berlaku')->afterOrEqual('mulai'),
                        FileUpload::make('file')
                            ->label('Arsip Dokumen Baru (PDF/JPG/PNG)')
                            ->disk(StorageQuotaService::diskName())
                            ->directory('kontrak-kerja')
                            ->acceptedFileTypes(self::ACCEPTED_FILE_TYPES) // WAJIB — WP-08
                            ->maxSize(5120),
                    ])
                    ->action(function (KontrakKerja $r, array $data): void {
                        try {
                            app(KontrakService::class)->perpanjang($r, $data);
                            Notification::make()->title('Kontrak diperpanjang')
                                ->body('Baris baru dibuat; arsip lama tetap utuh (status: diperpanjang).')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal memperpanjang')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('purna')->label('Purna')->icon('heroicon-o-flag')->color('gray')
                    ->visible(fn (KontrakKerja $r): bool => $r->jenis === 'sk_pengabdian'
                        && $r->tanggal_purna === null
                        && (auth()->user()?->can('run_payroll') ?? false))
                    ->schema([
                        DatePicker::make('tanggal_purna')->label('Tanggal Purna')->required()->default(today()),
                    ])
                    ->action(function (KontrakKerja $r, array $data): void {
                        app(KontrakService::class)->purna($r, $data['tanggal_purna']);
                        Notification::make()->title('Purna pengabdian dicatat')
                            ->body('Bila pegawai lanjut berkhidmat, buat penugasan baru via menu Penugasan Pegawai (WP-03).')
                            ->success()->persistent()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKontrakKerja::route('/'),
            'create' => CreateKontrakKerja::route('/create'),
            'edit' => EditKontrakKerja::route('/{record}/edit'),
        ];
    }
}
