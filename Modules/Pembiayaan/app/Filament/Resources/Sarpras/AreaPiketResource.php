<?php

namespace Modules\Pembiayaan\Filament\Resources\Sarpras;

use App\Filament\Concerns\ModuleGate;
use Modules\Pembiayaan\Filament\Resources\Sarpras\AreaPiket\Pages\CreateAreaPiket;
use Modules\Pembiayaan\Filament\Resources\Sarpras\AreaPiket\Pages\EditAreaPiket;
use Modules\Pembiayaan\Filament\Resources\Sarpras\AreaPiket\Pages\ListAreaPiket;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\Pembiayaan\Models\AreaPiket;
use UnitEnum;

/**
 * WP-33 — Master area piket + kode QR (dicetak, lalu di-scan via
 * keyboard-wedge di PWA Guru — pola gerbang).
 */
class AreaPiketResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = AreaPiket::class;

    protected static ?string $slug = 'sarpras/area-piket';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationLabel = 'Area Piket';

    protected static ?string $modelLabel = 'Area Piket';

    protected static UnitEnum|string|null $navigationGroup = 'Sarana & Logistik';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('nama')
                ->label('Nama Area')
                ->required()
                ->maxLength(255),
            TextInput::make('kode_qr')
                ->label('Kode QR')
                ->helperText('Unik per pesantren — isi kode QR/barcode yang ditempel di area. Kosongkan untuk digenerate otomatis.')
                ->maxLength(100)
                ->default(fn (): string => 'PIKET-'.Str::upper(Str::random(8)))
                ->required(),
            Toggle::make('aktif')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Area')->searchable()->sortable(),
                TextColumn::make('kode_qr')->label('Kode QR')->copyable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->actions([
                EditAction::make(),
                // gap-121 — cetak stiker QR (buka tab print, auto window.print).
                Action::make('cetak_qr')
                    ->label('Cetak QR')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (AreaPiket $record): string => route('tenant.sarpras.area-piket.cetak-qr', ['area' => $record->id]), true),
            ])
            ->defaultSort('nama');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAreaPiket::route('/'),
            'create' => CreateAreaPiket::route('/create'),
            'edit' => EditAreaPiket::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('pembiayaan') && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('pembiayaan') && auth()->check();
    }
}
