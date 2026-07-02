<?php

namespace Modules\Mutu\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Mutu\Filament\Resources\Pages\CreateDokumenMutu;
use Modules\Mutu\Filament\Resources\Pages\EditDokumenMutu;
use Modules\Mutu\Filament\Resources\Pages\ListDokumenMutu;
use Modules\Mutu\Models\DokumenMutu;
use Modules\Mutu\Services\MutuService;

class DokumenMutuResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = DokumenMutu::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Dosir Mutu (SYAMIL)';

    protected static ?string $modelLabel = 'Dokumen Mutu';

    protected static ?string $pluralModelLabel = 'Dokumen Mutu';

    protected static string|\UnitEnum|null $navigationGroup = 'Mutu';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('mutu') && (auth()->user()?->can('manage_mutu') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dokumen Mutu (SPMI/SPME)')->schema([
                TextInput::make('judul')->label('Judul')->required()->maxLength(255),
                Select::make('jenis')->label('Jenis')->options([
                    'SOP' => 'SOP', 'Hasil SKM' => 'Hasil SKM', 'MoU' => 'MoU', 'SK' => 'SK Tim', 'Rubrik' => 'Rubrik',
                ])->required(),
                TextInput::make('regulasi_acuan')->label('Regulasi Acuan')->placeholder('KMA 939/2024 / KMA 1495/2025')->maxLength(255),
                TextInput::make('berkas_url')->label('Berkas (URL/path)')->maxLength(255),
                Select::make('status_kelengkapan')->label('Kelengkapan')->options([
                    'lengkap' => 'Lengkap', 'kurang' => 'Belum/ Kurang',
                ])->default('kurang')->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')->label('Judul')->searchable(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('regulasi_acuan')->label('Regulasi')->placeholder('—'),
                TextColumn::make('status_kelengkapan')->label('Kelengkapan')->badge()
                    ->color(fn (?string $state): string => $state === 'lengkap' ? 'success' : 'warning')
                    ->formatStateUsing(fn (?string $state): string => $state === 'lengkap' ? 'Lengkap' : 'Belum tersedia'),
                TextColumn::make('status_approval')->label('Approval')->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'disetujui' => 'success',
                        'diajukan' => 'warning',
                        'ditolak' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('versi')->label('Versi')->badge()->color('gray'),
                TextColumn::make('catatan_review')->label('Catatan Review')
                    ->placeholder('—')->wrap()->toggleable()
                    ->tooltip(fn (?string $state): ?string => $state),
            ])
            ->defaultSort('jenis')
            ->filters([
                SelectFilter::make('status_kelengkapan')->options(['lengkap' => 'Lengkap', 'kurang' => 'Belum']),
                SelectFilter::make('status_approval')->options([
                    'draft' => 'Draft', 'diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak',
                ]),
            ])
            ->actions([
                // Ajukan: draft → diajukan (penyusun, manage_mutu).
                Action::make('ajukan')
                    ->label('Ajukan')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (DokumenMutu $record): bool => $record->status_approval === 'draft' && (auth()->user()?->can('manage_mutu') ?? false))
                    ->action(function (DokumenMutu $record): void {
                        try {
                            app(MutuService::class)->ajukan($record, (int) auth()->id());
                            Notification::make()->title('Dokumen diajukan untuk persetujuan')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal mengajukan dokumen')->body($e->getMessage())->danger()->send();
                        }
                    }),
                // Setujui: diajukan → disetujui (penyetuju, approve_mutu — segregasi tugas).
                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (DokumenMutu $record): bool => $record->status_approval === 'diajukan' && (auth()->user()?->can('approve_mutu') ?? false))
                    ->action(function (DokumenMutu $record): void {
                        try {
                            app(MutuService::class)->setujui($record, (int) auth()->id());
                            Notification::make()->title('Dokumen disetujui')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal menyetujui dokumen')->body($e->getMessage())->danger()->send();
                        }
                    }),
                // Tolak: diajukan → ditolak (approve_mutu). Alasan rinci = enhancement form.
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalDescription('Dokumen dikembalikan sebagai ditolak. Alasan akan terlihat oleh penyusun.')
                    ->schema([
                        Textarea::make('alasan')
                            ->label('Alasan penolakan')
                            ->required()
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Jelaskan kekurangan dokumen agar penyusun dapat merevisi.'),
                    ])
                    ->visible(fn (DokumenMutu $record): bool => $record->status_approval === 'diajukan' && (auth()->user()?->can('approve_mutu') ?? false))
                    ->action(function (array $data, DokumenMutu $record): void {
                        try {
                            app(MutuService::class)->tolak($record, (int) auth()->id(), $data['alasan']);
                            Notification::make()->title('Dokumen ditolak')->warning()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal menolak dokumen')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDokumenMutu::route('/'),
            'create' => CreateDokumenMutu::route('/create'),
            'edit' => EditDokumenMutu::route('/{record}/edit'),
        ];
    }
}
