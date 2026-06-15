<?php

namespace App\Filament\Resources\Alumni;

use App\Filament\Resources\Alumni\Pages\EditAlumni;
use App\Filament\Resources\Alumni\Pages\ListAlumni;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Services\KelulusanService;
use Modules\Kesantrian\Models\Alumni;
use Throwable;

/**
 * WP-08 — Buku alumni: arsip nomor ijazah negara (input manual TU),
 * penerbitan nomor syahadah (counter race-safe), dan cetak syahadah
 * dari template per lembaga.
 */
class AlumniResource extends Resource
{
    protected static ?string $model = Alumni::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Alumni';

    protected static ?string $modelLabel = 'Alumni';

    protected static ?string $pluralModelLabel = 'Alumni';

    protected static string|\UnitEnum|null $navigationGroup = 'Alumni';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // Alumni dibuat lewat proses kelulusan, bukan manual
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false; // arsip alumni tidak dihapus
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Arsip Ijazah Negara')
                ->description('Nomor ijazah resmi dari negara — diinput manual oleh TU setelah ijazah fisik terbit.')
                ->schema([
                    TextInput::make('nomor_ijazah')->label('Nomor Ijazah Negara')->maxLength(255)->nullable(),
                    TextInput::make('jenis_ijazah')->label('Jenis Ijazah')
                        ->placeholder('mis. Ijazah MTs / Paket B / SKL')->maxLength(255)->nullable(),
                ])->columns(2),
            Section::make('Data Lanjutan')
                ->schema([
                    TextInput::make('status_lanjutan')->label('Status Lanjutan')
                        ->placeholder('kuliah / kerja / pesantren lanjutan')->maxLength(255)->nullable(),
                    TextInput::make('sekolah_tujuan')->label('Sekolah/Kampus Tujuan')->maxLength(255)->nullable(),
                    Textarea::make('catatan')->rows(3)->nullable()->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada alumni')
            ->emptyStateDescription('Data alumni akan muncul setelah santri dinyatakan lulus.')
            ->emptyStateIcon('heroicon-o-academic-cap')
            ->columns([
                TextColumn::make('santri.nis')
                    ->label('NIS')
                    ->searchable(),
                TextColumn::make('santri.nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tahun_lulus')
                    ->label('Tahun Lulus')
                    ->sortable(),
                TextColumn::make('lembaga.nama')
                    ->label('Lembaga')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('nomor_syahadah')
                    ->label('No. Syahadah')
                    ->placeholder('belum terbit'),
                TextColumn::make('nomor_ijazah')
                    ->label('No. Ijazah Negara')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status_lanjutan')
                    ->label('Status Lanjutan')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->defaultSort('tahun_lulus', 'desc')
            ->filters([
                SelectFilter::make('tahun_lulus')
                    ->options(fn () => Alumni::distinct()->pluck('tahun_lulus', 'tahun_lulus')->toArray()),
            ])
            ->actions([
                EditAction::make()->label('Arsip Ijazah'),
                Action::make('terbitkan_syahadah')
                    ->label('Terbitkan No. Syahadah')
                    ->icon('heroicon-o-document-check')
                    ->requiresConfirmation()
                    ->visible(fn (Alumni $record): bool => $record->nomor_syahadah === null
                        && (auth()->user()?->can('manage_santri') ?? false))
                    ->action(function (Alumni $record): void {
                        try {
                            $alumni = app(KelulusanService::class)->generateNomorSyahadah($record);
                            Notification::make()->success()
                                ->title('Nomor syahadah terbit: '.$alumni->nomor_syahadah)->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Gagal menerbitkan nomor')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('cetak_syahadah')
                    ->label('Cetak Syahadah')
                    ->icon('heroicon-o-printer')
                    ->visible(fn (Alumni $record): bool => $record->nomor_syahadah !== null)
                    ->url(fn (Alumni $record): string => route('tenant.akademik.export.syahadah.print', ['alumni' => $record]))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['santri', 'lembaga']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlumni::route('/'),
            'edit' => EditAlumni::route('/{record}/edit'),
        ];
    }
}
