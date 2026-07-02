<?php

namespace Modules\PPDB\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Joaopaulolndev\FilamentPdfViewer\Infolists\Components\PdfViewerEntry;
use Modules\PPDB\Filament\Resources\CalonSantriDokumen\Pages\CreateCalonSantriDokumen;
use Modules\PPDB\Filament\Resources\CalonSantriDokumen\Pages\EditCalonSantriDokumen;
use Modules\PPDB\Filament\Resources\CalonSantriDokumen\Pages\ListCalonSantriDokumen;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\CalonSantriDokumen;
use Modules\PPDB\Services\PpdbWhatsappNotifier;
use Modules\PPDB\Support\PpdbDocumentTypes;

class CalonSantriDokumenResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = CalonSantriDokumen::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationLabel = 'Dokumen PPDB';

    protected static ?string $modelLabel = 'Dokumen PPDB';

    protected static ?string $pluralModelLabel = 'Dokumen PPDB';

    protected static string|\UnitEnum|null $navigationGroup = 'Pendaftaran';

    protected static ?string $slug = 'ppdb/dokumen';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ppdb') && (static::canAccess());
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ppdb') && (Auth::user()?->canAny(['view_ppdb', 'manage_ppdb']) ?? false);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('manage_ppdb') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->can('manage_ppdb') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->can('manage_ppdb') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dokumen Calon Santri')->schema([
                Select::make('calon_santri_id')
                    ->label('Calon Santri')
                    ->options(fn () => CalonSantri::orderBy('nama')->pluck('nama', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('jenis_dokumen')
                    ->label('Jenis Dokumen')
                    ->required()
                    ->maxLength(100),
                TextInput::make('file_url')
                    ->label('File URL')
                    ->required()
                    ->maxLength(255)
                    ->url(),
            ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('calonSantri.nama')
                    ->label('Calon Santri')
                    ->searchable(),
                TextColumn::make('jenis_dokumen')
                    ->label('Jenis Dokumen')
                    ->sortable(),
                TextColumn::make('file_url')
                    ->label('File')
                    ->limit(40),
                TextColumn::make('status')
                    ->label('Verifikasi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => CalonSantriDokumen::STATUS_LABELS[$state ?? CalonSantriDokumen::STATUS_PENDING] ?? 'Menunggu Verifikasi')
                    ->color(fn (?string $state): string => match ($state) {
                        CalonSantriDokumen::STATUS_VERIFIED => 'success',
                        CalonSantriDokumen::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('id')
            ->actions([
                // Preview in-panel: dokumen PDF tampil di PDF viewer, gambar
                // (jpg/png ijazah scan) tampil sebagai ImageEntry, dalam modal.
                // Dokumen tipe lain (belum bisa di-preview) tetap unduh tab baru.
                Action::make('lihatFile')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    // Privasi foto putri (#340): pas foto calon PEREMPUAN hanya
                    // bisa dibuka peran berhak (pimpinan/super admin).
                    ->visible(fn (CalonSantriDokumen $record): bool => $record->file_url !== null
                        && static::fotoCalonBolehDilihat($record))
                    // Dokumen tak-terpreview (bukan pdf/gambar) → unduh tab baru
                    // memakai route yang sama (auth + gerbang privasi utuh).
                    ->url(
                        fn (CalonSantriDokumen $record): ?string => static::dokumenDapatDipreview($record)
                            ? null
                            : route('ppdb.file.dokumen', $record),
                        shouldOpenInNewTab: true,
                    )
                    ->modalHeading(fn (CalonSantriDokumen $record): string => 'Pratinjau: '.PpdbDocumentTypes::label($record->jenis_dokumen))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('4xl')
                    // Modal hanya untuk dokumen yang bisa dipreview in-panel.
                    ->schema(fn (CalonSantriDokumen $record): array => static::dokumenDapatDipreview($record)
                        ? [static::previewEntryUntuk($record)]
                        : []),
                Action::make('verifikasi')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CalonSantriDokumen $record): bool => ! $record->isVerified() && (Auth::user()?->can('manage_ppdb') ?? false))
                    ->action(function (CalonSantriDokumen $record): void {
                        $record->update([
                            'status' => CalonSantriDokumen::STATUS_VERIFIED,
                            'verified_at' => now(),
                            'verified_by' => Auth::id(),
                            'catatan_verifikasi' => null,
                        ]);
                        $calon = $record->calonSantri;
                        if ($calon && $calon->no_hp_wali) {
                            app(PpdbWhatsappNotifier::class)->sendDokumenDiverifikasi(
                                (string) $calon->no_hp_wali,
                                (string) $calon->nama,
                                (string) $calon->nomor_pendaftaran,
                                PpdbDocumentTypes::label($record->jenis_dokumen),
                            );
                        }
                    }),
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (CalonSantriDokumen $record): bool => ! $record->isRejected() && (Auth::user()?->can('manage_ppdb') ?? false))
                    ->schema([
                        Textarea::make('catatan_verifikasi')
                            ->label('Alasan penolakan')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (CalonSantriDokumen $record, array $data): void {
                        $record->update([
                            'status' => CalonSantriDokumen::STATUS_REJECTED,
                            'catatan_verifikasi' => $data['catatan_verifikasi'],
                            'verified_at' => now(),
                            'verified_by' => Auth::id(),
                        ]);
                        $calon = $record->calonSantri;
                        if ($calon && $calon->no_hp_wali) {
                            app(PpdbWhatsappNotifier::class)->sendDokumenDitolak(
                                (string) $calon->no_hp_wali,
                                (string) $calon->nama,
                                (string) $calon->nomor_pendaftaran,
                                PpdbDocumentTypes::label($record->jenis_dokumen),
                                (string) $data['catatan_verifikasi'],
                            );
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Privasi foto putri (#340): dokumen jenis 'foto' milik calon santri
     * PEREMPUAN hanya boleh dibuka peran berhak (Santri::PERAN_LIHAT_FOTO_PUTRI).
     * Dokumen lain (ijazah, KK, akta) tidak digerbang.
     */
    public static function fotoCalonBolehDilihat(CalonSantriDokumen $record): bool
    {
        if ($record->jenis_dokumen !== 'foto') {
            return true;
        }

        return $record->calonSantri?->fotoDapatDilihat() ?? true;
    }

    /** Ekstensi file (lowercase) dari path/URL dokumen. */
    protected static function ekstensiDokumen(CalonSantriDokumen $record): string
    {
        $path = (string) parse_url((string) $record->file_url, PHP_URL_PATH);
        if ($path === '') {
            $path = (string) $record->file_url;
        }

        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    protected static function dokumenPdf(CalonSantriDokumen $record): bool
    {
        return static::ekstensiDokumen($record) === 'pdf';
    }

    protected static function dokumenGambar(CalonSantriDokumen $record): bool
    {
        return in_array(static::ekstensiDokumen($record), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    /** Dokumen bisa dipreview in-panel bila PDF atau gambar. */
    protected static function dokumenDapatDipreview(CalonSantriDokumen $record): bool
    {
        return static::dokumenPdf($record) || static::dokumenGambar($record);
    }

    /**
     * URL serve inline (bukan unduh) via PpdbFileController — auth + gerbang
     * privasi #340 tetap berlaku; path/disk tidak pernah bocor ke klien.
     */
    protected static function urlPreview(CalonSantriDokumen $record): string
    {
        return route('ppdb.file.dokumen', ['dokumen' => $record, 'inline' => 1]);
    }

    /**
     * Komponen preview sesuai tipe: PDF → PdfViewerEntry (iframe in-panel),
     * gambar → ImageEntry. Keduanya menunjuk ke URL inline yang tergerbang.
     */
    protected static function previewEntryUntuk(CalonSantriDokumen $record)
    {
        if (static::dokumenPdf($record)) {
            return PdfViewerEntry::make('preview_pdf')
                ->hiddenLabel()
                ->fileUrl(static::urlPreview($record))
                ->minHeight('70svh')
                ->columnSpanFull();
        }

        return ImageEntry::make('preview_gambar')
            ->hiddenLabel()
            ->state(static::urlPreview($record))
            ->imageHeight('60svh')
            ->columnSpanFull();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('calonSantri');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalonSantriDokumen::route('/'),
            'create' => CreateCalonSantriDokumen::route('/create'),
            'edit' => EditCalonSantriDokumen::route('/{record}/edit'),
        ];
    }
}
