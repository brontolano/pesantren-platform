<?php

namespace Modules\Kegiatan\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use Modules\Kegiatan\Filament\Resources\KunjunganWali\Pages\CreateKunjunganWali;
use Modules\Kegiatan\Filament\Resources\KunjunganWali\Pages\EditKunjunganWali;
use Modules\Kegiatan\Filament\Resources\KunjunganWali\Pages\ListKunjunganWali;
use App\Support\UiMode;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Kegiatan\Models\KunjunganWali;
use UnitEnum;

class KunjunganWaliResource extends Resource
{
    /** Mode simple: menu lanjutan disembunyikan (fungsi & route tetap aktif). */
    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced();
    }

    use ModuleGate;

    protected static ?string $model = KunjunganWali::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Kunjungan Wali';

    protected static UnitEnum|string|null $navigationGroup = 'Santri & Pengasuhan';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('santri_id')
                ->label('Santri yang Dikunjungi')
                ->relationship('santri', 'nama')
                ->searchable()->preload()->required(),
            TextInput::make('nama_pengunjung')->label('Nama Pengunjung')->required()->maxLength(255),
            Select::make('hubungan')
                ->label('Hubungan')
                ->options(['ayah' => 'Ayah', 'ibu' => 'Ibu', 'saudara' => 'Saudara', 'wali' => 'Wali'])
                ->required(),
            DatePicker::make('tanggal')->label('Tanggal Kunjungan')->required()->default(today()),
            TimePicker::make('jam_datang')->label('Jam Datang'),
            TimePicker::make('jam_pulang')->label('Jam Pulang'),
            Textarea::make('keperluan')->label('Keperluan')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('nama_pengunjung')->label('Pengunjung')->searchable(),
                TextColumn::make('hubungan')->label('Hubungan')->badge(),
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('jam_datang')->label('Datang'),
                TextColumn::make('jam_pulang')->label('Pulang'),
            ])
            ->actions([EditAction::make()])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKunjunganWali::route('/'),
            'create' => CreateKunjunganWali::route('/create'),
            'edit' => EditKunjunganWali::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('kegiatan') && (auth()->user()?->can('view_santri') ?? false);
    }
}
