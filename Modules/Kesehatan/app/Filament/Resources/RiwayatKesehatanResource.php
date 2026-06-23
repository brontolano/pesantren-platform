<?php

namespace Modules\Kesehatan\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use Modules\Kesehatan\Filament\Resources\RiwayatKesehatan\Pages\CreateRiwayatKesehatan;
use Modules\Kesehatan\Filament\Resources\RiwayatKesehatan\Pages\EditRiwayatKesehatan;
use Modules\Kesehatan\Filament\Resources\RiwayatKesehatan\Pages\ListRiwayatKesehatan;
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
use Modules\Kesehatan\Models\RiwayatKesehatan;
use UnitEnum;

class RiwayatKesehatanResource extends Resource
{
    /** Mode simple: menu lanjutan disembunyikan (fungsi & route tetap aktif). */
    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced();
    }

    use ModuleGate;

    protected static ?string $model = RiwayatKesehatan::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Riwayat Kesehatan';

    protected static UnitEnum|string|null $navigationGroup = 'UKS';

    protected static ?int $navigationSort = 1;

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
                ->label('Tanggal')
                ->required()
                ->default(now()),
            TextInput::make('keluhan')
                ->label('Keluhan')
                ->maxLength(255),
            TextInput::make('diagnosa')
                ->label('Diagnosa')
                ->maxLength(255),
            Textarea::make('tindakan')
                ->label('Tindakan')
                ->rows(3),
            Select::make('kondisi')
                ->label('Kondisi')
                ->options([
                    'baik' => 'Baik',
                    'sakit' => 'Sakit',
                    'rujuk' => 'Rujuk ke RS',
                ])
                ->required()
                ->default('baik'),
            TextInput::make('petugas')
                ->label('Petugas UKS')
                ->maxLength(255),
            Textarea::make('catatan')
                ->label('Catatan')
                ->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('keluhan')->label('Keluhan')->limit(40),
                TextColumn::make('kondisi')->label('Kondisi')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sakit' => 'danger',
                        'rujuk' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('petugas')->label('Petugas'),
            ])
            ->filters([
                SelectFilter::make('kondisi')
                    ->options(['baik' => 'Baik', 'sakit' => 'Sakit', 'rujuk' => 'Rujuk']),
            ])
            ->actions([EditAction::make()])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRiwayatKesehatan::route('/'),
            'create' => CreateRiwayatKesehatan::route('/create'),
            'edit' => EditRiwayatKesehatan::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('kesehatan') && (auth()->user()?->can('view_santri') ?? false);
    }
}
