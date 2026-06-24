<?php

namespace Modules\PPDB\Filament\Resources;

use Modules\PPDB\Filament\Resources\CalonSantriDokumen\Pages\CreateCalonSantriDokumen;
use Modules\PPDB\Filament\Resources\CalonSantriDokumen\Pages\EditCalonSantriDokumen;
use Modules\PPDB\Filament\Resources\CalonSantriDokumen\Pages\ListCalonSantriDokumen;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\CalonSantriDokumen;
use Modules\PPDB\Services\PpdbWhatsappNotifier;
use Modules\PPDB\Support\PpdbDocumentTypes;

class CalonSantriDokumenResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

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
        return static::moduleVisible('ppdb') && (Auth::user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
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
                Action::make('lihatFile')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (CalonSantriDokumen $record): bool => $record->file_url !== null)
                    ->url(fn (CalonSantriDokumen $record): string => route('ppdb.file.dokumen', $record), shouldOpenInNewTab: true),
                Action::make('verifikasi')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CalonSantriDokumen $record): bool => ! $record->isVerified() && (Auth::user()?->can('manage_santri') ?? false))
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
                    ->visible(fn (CalonSantriDokumen $record): bool => ! $record->isRejected() && (Auth::user()?->can('manage_santri') ?? false))
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

