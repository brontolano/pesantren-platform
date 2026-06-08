<?php

namespace App\Filament\Resources\Ledger;

use App\Filament\Resources\Ledger\Pages\CreateAkun;
use App\Filament\Resources\Ledger\Pages\EditAkun;
use App\Filament\Resources\Ledger\Pages\ListAkun;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Ledger\Models\Akun;

class AkunResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Akun::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Bagan Akun (COA)';

    protected static ?string $modelLabel = 'Akun';

    protected static ?string $pluralModelLabel = 'Bagan Akun';

    protected static string|\UnitEnum|null $navigationGroup = 'Buku Besar';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Cegah hapus akun yang sudah dipakai jurnal (integritas buku besar).
        if ($record instanceof Akun && $record->jurnalBaris()->exists()) {
            return false;
        }

        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun (COA)')->schema([
                TextInput::make('kode')->label('Kode')->required()->maxLength(20)
                    ->placeholder('1-1000'),
                TextInput::make('nama')->label('Nama Akun')->required()->maxLength(255),
                Select::make('tipe')->label('Tipe')->options([
                    'ASET' => 'ASET', 'KEWAJIBAN' => 'KEWAJIBAN', 'EKUITAS' => 'EKUITAS',
                    'PENDAPATAN' => 'PENDAPATAN', 'BEBAN' => 'BEBAN',
                ])->required()->live()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('saldo_normal', Akun::SALDO_NORMAL[$state] ?? 'debit')),
                Select::make('saldo_normal')->label('Saldo Normal')->options([
                    'debit' => 'Debit', 'kredit' => 'Kredit',
                ])->required(),
                Select::make('parent_id')->label('Akun Induk')
                    ->options(fn () => Akun::orderBy('kode')->pluck('nama', 'id'))
                    ->searchable()->placeholder('— akun teratas —'),
                Select::make('peruntukan')->label('Peruntukan (untuk Dana Kebajikan)')->options([
                    'umum' => 'Umum',
                    'ibadah' => 'Ibadah / Masjid (terlarang utk syubhat)',
                    'dhuafa' => 'Dhuafa',
                    'fasilitas_umum' => 'Fasilitas Umum',
                    'operasional' => 'Operasional Non-Ibadah',
                ])->placeholder('—'),
                Toggle::make('is_kas_bank')->label('Akun Kas/Bank'),
                Toggle::make('boleh_dana_kebajikan')->label('Boleh Menerima Dana Kebajikan'),
                Toggle::make('is_postable')->label('Bisa Diposting')->default(true),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->sortable()->searchable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('tipe')->label('Tipe')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ASET' => 'info',
                        'KEWAJIBAN' => 'warning',
                        'EKUITAS' => 'gray',
                        'PENDAPATAN' => 'success',
                        'BEBAN' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('saldo_normal')->label('Saldo Normal')->badge(),
                TextColumn::make('parent.kode')->label('Induk')->placeholder('—'),
                IconColumn::make('is_kas_bank')->label('Kas/Bank')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('kode')
            ->filters([
                SelectFilter::make('tipe')->options([
                    'ASET' => 'ASET', 'KEWAJIBAN' => 'KEWAJIBAN', 'EKUITAS' => 'EKUITAS',
                    'PENDAPATAN' => 'PENDAPATAN', 'BEBAN' => 'BEBAN',
                ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAkun::route('/'),
            'create' => CreateAkun::route('/create'),
            'edit' => EditAkun::route('/{record}/edit'),
        ];
    }
}
