<?php

namespace Modules\Akademik\Filament\Resources\PresensiSantri;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Forms\SantriSelect;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Akademik\Filament\Resources\PresensiSantri\Pages\CreatePresensiSantri;
use Modules\Akademik\Filament\Resources\PresensiSantri\Pages\EditPresensiSantri;
use Modules\Akademik\Filament\Resources\PresensiSantri\Pages\ListPresensiSantri;
use Modules\Akademik\Models\PresensiSantri;

class PresensiSantriResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = PresensiSantri::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Presensi Harian';

    protected static ?string $modelLabel = 'Presensi Santri';

    protected static ?string $pluralModelLabel = 'Presensi Harian';

    protected static string|\UnitEnum|null $navigationGroup = 'Pembelajaran';

    protected static ?int $navigationSort = 8;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Presensi Kelas Harian')->schema([
                SantriSelect::make('santri_id')->required(),
                DatePicker::make('tanggal')->label('Tanggal')->required()->default(now()),
                Select::make('status')->label('Status')->native(false)->required()
                    ->options(array_combine(PresensiSantri::STATUS, ['Hadir', 'Izin', 'Sakit', 'Alpa']))->default('hadir'),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date()->sortable(),
                TextColumn::make('santri.nama')->label('Santri')->searchable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (?string $s): string => match ($s) {
                        'hadir' => 'success', 'izin' => 'info', 'sakit' => 'warning', default => 'danger',
                    }),
                TextColumn::make('keterangan')->label('Keterangan')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('status')->options(array_combine(PresensiSantri::STATUS, PresensiSantri::STATUS)),
            ])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPresensiSantri::route('/'),
            'create' => CreatePresensiSantri::route('/create'),
            'edit' => EditPresensiSantri::route('/{record}/edit'),
        ];
    }
}
