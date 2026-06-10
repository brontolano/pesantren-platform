<?php

namespace App\Filament\Resources\Payroll\Employee;

use App\Filament\Resources\Payroll\Employee\Pages\CreateEmployee;
use App\Filament\Resources\Payroll\Employee\Pages\EditEmployee;
use App\Filament\Resources\Payroll\Employee\Pages\ListEmployee;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Payroll\Models\Employee;

/**
 * G2-10 — Master Pegawai + Struktur Gaji.
 */
class EmployeeResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Employee::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Pegawai (HR)';

    protected static ?string $modelLabel = 'Pegawai';

    protected static ?string $pluralModelLabel = 'Pegawai';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 24;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('payroll') && (auth()->user()?->can('run_payroll') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('run_payroll') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('run_payroll') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Pegawai')->schema([
                TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                Select::make('users_id')->label('Akun Pengguna (untuk slip)')->searchable()
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->helperText('Wajib untuk generate slip gaji.'),
                TextInput::make('nip')->label('NIP')->maxLength(40),
                TextInput::make('jabatan')->label('Jabatan')->maxLength(255),
                Select::make('status_kepegawaian')->label('Status')->native(false)->default('honorer')
                    ->options(['tetap' => 'Tetap', 'honorer' => 'Honorer', 'kontrak' => 'Kontrak']),
                TextInput::make('npwp')->label('NPWP')->maxLength(30),
                Select::make('ptkp_status')->label('Status PTKP (PPh21)')->native(false)->default('TK0')
                    ->options(['TK0' => 'TK/0', 'TK1' => 'TK/1', 'TK2' => 'TK/2', 'TK3' => 'TK/3',
                        'K0' => 'K/0', 'K1' => 'K/1', 'K2' => 'K/2', 'K3' => 'K/3']),
                DatePicker::make('tanggal_masuk')->label('Tanggal Masuk'),
                Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
            ])->columns(2),
            Section::make('Struktur Gaji')->schema([
                Repeater::make('komponenGaji')->label('Komponen Gaji')->relationship('komponenGaji')
                    ->schema([
                        TextInput::make('nama')->label('Komponen')->required()->columnSpan(2),
                        Select::make('tipe')->label('Tipe')->native(false)->required()->default('tetap')
                            ->options(['tetap' => 'Tetap (pokok)', 'tunjangan' => 'Tunjangan', 'potongan' => 'Potongan']),
                        TextInput::make('nominal')->label('Nominal')->numeric()->required()->prefix('Rp'),
                        Toggle::make('aktif')->label('Aktif')->default(true),
                    ])->columns(5)->defaultItems(1)->addActionLabel('Tambah Komponen'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('nip')->label('NIP')->placeholder('—'),
                TextColumn::make('jabatan')->label('Jabatan')->placeholder('—'),
                TextColumn::make('status_kepegawaian')->label('Status')->badge(),
                TextColumn::make('ptkp_status')->label('PTKP')->toggleable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->defaultSort('nama')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployee::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
