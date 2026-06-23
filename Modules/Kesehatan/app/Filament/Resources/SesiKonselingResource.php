<?php

namespace Modules\Kesehatan\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use Modules\Kesehatan\Filament\Resources\SesiKonseling\Pages\CreateSesiKonseling;
use Modules\Kesehatan\Filament\Resources\SesiKonseling\Pages\EditSesiKonseling;
use Modules\Kesehatan\Filament\Resources\SesiKonseling\Pages\ListSesiKonseling;
use App\Support\UiMode;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Kesehatan\Models\SesiKonseling;
use UnitEnum;

class SesiKonselingResource extends Resource
{
    /** Mode simple: menu lanjutan disembunyikan (fungsi & route tetap aktif). */
    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced();
    }

    use ModuleGate;

    protected static ?string $model = SesiKonseling::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Sesi Konseling';

    protected static UnitEnum|string|null $navigationGroup = 'UKS';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('santri_id')
                ->label('Santri')
                ->relationship('santri', 'nama')
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('tanggal')
                ->label('Tanggal Konseling')
                ->required()
                ->default(now()),
            TextInput::make('topik')
                ->label('Topik')
                ->maxLength(255),
            Textarea::make('deskripsi')
                ->label('Deskripsi Masalah')
                ->rows(3),
            Select::make('status')
                ->label('Status')
                ->options([
                    'terjadwal' => 'Terjadwal',
                    'selesai' => 'Selesai',
                    'dibatalkan' => 'Dibatalkan',
                ])
                ->required()
                ->default('terjadwal'),
            Select::make('konselor_id')
                ->label('Konselor')
                ->relationship('konselor', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            Textarea::make('rekomendasi')
                ->label('Rekomendasi')
                ->rows(3),
            Textarea::make('catatan_lanjutan')
                ->label('Catatan Lanjutan')
                ->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('topik')->label('Topik')->limit(40),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'selesai' => 'success',
                        'dibatalkan' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('konselor.name')->label('Konselor'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['terjadwal' => 'Terjadwal', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan']),
            ])
            ->actions([EditAction::make()])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSesiKonseling::route('/'),
            'create' => CreateSesiKonseling::route('/create'),
            'edit' => EditSesiKonseling::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('kesehatan') && (auth()->user()?->can('view_santri') ?? false);
    }
}
