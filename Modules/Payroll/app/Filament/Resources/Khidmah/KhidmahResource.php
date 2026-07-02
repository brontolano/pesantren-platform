<?php

namespace Modules\Payroll\Filament\Resources\Khidmah;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Payroll\Filament\Resources\Khidmah\Pages\ListKhidmah;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Khidmah;

/**
 * G2-11 — Poin khidmah/layanan per pegawai per periode.
 */
class KhidmahResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Khidmah::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    protected static ?string $navigationLabel = 'Pengabdian (Khidmah)';

    protected static ?string $modelLabel = 'Khidmah';

    protected static ?string $pluralModelLabel = 'Khidmah';

    protected static string|\UnitEnum|null $navigationGroup = 'Penggajian';

    protected static ?int $navigationSort = 27;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('payroll') && (auth()->user()?->can('run_payroll') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Khidmah')->schema([
                Select::make('employee_id')->label('Pegawai')->required()->searchable()
                    ->options(fn () => Employee::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')),
                TextInput::make('periode')->label('Periode')->required()->placeholder('2026-06')->maxLength(7),
                TextInput::make('poin')->label('Poin')->numeric()->required()->default(0),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.nama')->label('Pegawai')->searchable(),
                TextColumn::make('periode')->label('Periode')->sortable(),
                TextColumn::make('poin')->label('Poin')->badge()->color('info')->sortable(),
                TextColumn::make('keterangan')->label('Keterangan')->placeholder('—'),
            ])
            ->defaultSort('periode', 'desc')
            ->headerActions([CreateAction::make()->label('Tambah Khidmah')])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListKhidmah::route('/')];
    }
}
