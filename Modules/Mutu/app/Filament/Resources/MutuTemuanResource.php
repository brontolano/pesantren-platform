<?php

namespace Modules\Mutu\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use Modules\Mutu\Filament\Resources\MutuTemuan\Pages\CreateMutuTemuan;
use Modules\Mutu\Filament\Resources\MutuTemuan\Pages\EditMutuTemuan;
use Modules\Mutu\Filament\Resources\MutuTemuan\Pages\ListMutuTemuan;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use Modules\Mutu\Models\MutuInspeksi;
use Modules\Mutu\Models\MutuTemuan;
use Modules\Mutu\Services\MutuKpiService;

class MutuTemuanResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = MutuTemuan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Temuan (Non-Conformance)';

    protected static ?string $modelLabel = 'Temuan Mutu';

    protected static ?string $pluralModelLabel = 'Temuan (Non-Conformance)';

    protected static string|\UnitEnum|null $navigationGroup = 'Mutu & Laporan';

    protected static ?int $navigationSort = 3;

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
            Section::make('Temuan Ketidaksesuaian')->schema([
                TextInput::make('judul')->label('Judul')->required()->maxLength(255),
                Select::make('severity')->label('Severity')->native(false)->options(MutuTemuan::SEVERITY)->default('minor')->required()
                    // Terkunci setelah tindakan dimulai (cegah bypass gerbang approval).
                    ->disabled(fn (?MutuTemuan $record): bool => $record !== null && $record->status !== 'terbuka'),
                Select::make('inspeksi_id')->label('Inspeksi (opsional)')->native(false)->searchable()
                    ->options(fn (): array => MutuInspeksi::query()->orderByDesc('id')->limit(50)
                        ->get()->mapWithKeys(fn (MutuInspeksi $i): array => [$i->id => $i->judul])->all()),
                DatePicker::make('tenggat')->label('Tenggat'),
                Textarea::make('deskripsi')->label('Deskripsi')->rows(3)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')->label('Temuan')->searchable()->wrap(),
                TextColumn::make('severity')->label('Severity')->badge()
                    ->formatStateUsing(fn (?string $s): string => MutuTemuan::SEVERITY[$s] ?? $s)
                    ->color(fn (?string $s): string => match ($s) {
                        'kritis' => 'danger', 'major' => 'warning', default => 'gray'
                    }),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (?string $s): string => match ($s) {
                        'tutup' => 'success', 'verifikasi' => 'info', 'tindakan' => 'warning', default => 'danger',
                    }),
                TextColumn::make('tenggat')->label('Tenggat')->date()->placeholder('—'),
                TextColumn::make('diverifikasi_pada')->label('Ditutup')->dateTime()->placeholder('—')->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->options(array_combine(MutuTemuan::STATUS, MutuTemuan::STATUS)),
                SelectFilter::make('severity')->options(MutuTemuan::SEVERITY),
            ])
            ->actions([
                Action::make('mulai_tindakan')
                    ->label('Mulai Tindakan')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (MutuTemuan $r): bool => $r->status === 'terbuka')
                    ->schema([
                        Textarea::make('tindakan')->label('Tindakan korektif')->required()->rows(3),
                    ])
                    ->action(function (array $data, MutuTemuan $record): void {
                        try {
                            app(MutuKpiService::class)->mulaiTindakan($record, $data['tindakan'], auth()->id());
                            Notification::make()->title('Tindakan korektif dicatat')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal mencatat tindakan')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('ajukan_verifikasi')
                    ->label('Ajukan Verifikasi')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (MutuTemuan $r): bool => $r->status === 'tindakan')
                    ->action(function (MutuTemuan $record): void {
                        try {
                            app(MutuKpiService::class)->ajukanVerifikasi($record, auth()->id());
                            Notification::make()->title('Diajukan untuk verifikasi')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal mengajukan verifikasi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('tutup')
                    ->label('Tutup (Verifikasi)')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MutuTemuan $r): bool => $r->status === 'verifikasi' && (auth()->user()?->can('approve_mutu') ?? false))
                    ->action(function (MutuTemuan $record): void {
                        try {
                            app(MutuKpiService::class)->tutup($record, auth()->id());
                            Notification::make()->title('Temuan ditutup')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal menutup')->body($e->getMessage())->danger()->send();
                        }
                    }),
                // Edit hanya saat 'terbuka' — setelah itu append-only via aksi resmi
                // (mulai_tindakan/ajukan_verifikasi/tutup), menjaga integritas audit.
                EditAction::make()->visible(fn (MutuTemuan $r): bool => $r->status === 'terbuka'),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMutuTemuan::route('/'),
            'create' => CreateMutuTemuan::route('/create'),
            'edit' => EditMutuTemuan::route('/{record}/edit'),
        ];
    }
}
