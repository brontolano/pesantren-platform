<?php

namespace App\Filament\Resources\Payroll\PresensiStaf;

use App\Filament\Resources\Payroll\PresensiStaf\Pages\ListPresensiStaf;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PresensiStaf;

/**
 * G2-11 — Presensi staf harian.
 */
class PresensiStafResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = PresensiStaf::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Presensi Staf';

    protected static ?string $modelLabel = 'Presensi Staf';

    protected static ?string $pluralModelLabel = 'Presensi Staf';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 26;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('payroll') && (auth()->user()?->can('run_payroll') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Presensi')->schema([
                Select::make('employee_id')->label('Pegawai')->required()->searchable()
                    ->options(fn () => Employee::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')),
                DatePicker::make('tanggal')->label('Tanggal')->required()->default(now()),
                Select::make('status')->label('Status')->native(false)->default('hadir')
                    ->options(['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa', 'cuti' => 'Cuti']),
                TimePicker::make('jam_masuk')->label('Jam Masuk')->seconds(false),
                TimePicker::make('jam_keluar')->label('Jam Keluar')->seconds(false),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('employee.nama')->label('Pegawai')->searchable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'hadir' => 'success', 'alpa' => 'danger', 'cuti' => 'info', default => 'warning',
                    }),
                TextColumn::make('jam_masuk')->label('Masuk')->placeholder('—'),
                TextColumn::make('jam_keluar')->label('Keluar')->placeholder('—'),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('status')->options(['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa', 'cuti' => 'Cuti']),
            ])
            ->headerActions([CreateAction::make()->label('Catat Presensi')])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPresensiStaf::route('/'),
        ];
    }
}
