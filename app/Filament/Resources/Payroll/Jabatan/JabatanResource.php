<?php

namespace App\Filament\Resources\Payroll\Jabatan;

use App\Filament\Resources\Payroll\Jabatan\Pages\CreateJabatan;
use App\Filament\Resources\Payroll\Jabatan\Pages\EditJabatan;
use App\Filament\Resources\Payroll\Jabatan\Pages\ListJabatan;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Payroll\Models\Jabatan;
use Spatie\Permission\Models\Role;

/**
 * WP-03 — Master Jabatan (Blueprint SDM S-0 poin 1). Mapping role_spatie
 * memilih dari role yang SUDAH ada di DB tenant — tidak menciptakan role baru.
 */
class JabatanResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Jabatan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Jabatan';

    protected static ?string $modelLabel = 'Jabatan';

    protected static ?string $pluralModelLabel = 'Jabatan';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 25;

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
            Section::make('Jabatan')->schema([
                TextInput::make('nama')->label('Nama Jabatan')->required()->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('kategori')->label('Kategori')->native(false)->required()->default('staf')
                    ->options(Jabatan::KATEGORI),
                Select::make('role_spatie')->label('Role Akses (Spatie)')->native(false)->nullable()
                    ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'name'))
                    ->helperText('Penugasan jabatan ini otomatis memberi role tersebut ke akun pegawai. Kosongkan bila tanpa akses otomatis (mis. pengurus santri).'),
                Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada jabatan')
            ->emptyStateDescription('Jabatan bawaan dibuat otomatis saat provisioning.')
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('kategori')->label('Kategori')->badge()
                    ->formatStateUsing(fn (string $state): string => Jabatan::KATEGORI[$state] ?? $state),
                TextColumn::make('role_spatie')->label('Role Akses')->placeholder('—'),
                TextColumn::make('penugasan_count')->label('Penugasan')->counts('penugasan'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->defaultSort('nama')
            ->actions([EditAction::make(), static::guardedDeleteAction()]);
    }

    /**
     * FK penugasan restrictOnDelete: hapus jabatan ber-penugasan harus
     * ditolak RAPI (notifikasi), bukan QueryException 500.
     */
    public static function guardedDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->before(function (DeleteAction $action, Jabatan $record): void {
                if ($record->penugasan()->exists()) {
                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('Jabatan tidak bisa dihapus')
                        ->body('Masih ada penugasan (aktif atau riwayat) yang memakai jabatan ini. Nonaktifkan jabatan sebagai gantinya.')
                        ->send();

                    $action->cancel();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJabatan::route('/'),
            'create' => CreateJabatan::route('/create'),
            'edit' => EditJabatan::route('/{record}/edit'),
        ];
    }
}
