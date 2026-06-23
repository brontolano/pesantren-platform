<?php

namespace Modules\Ledger\Filament\Resources\AlokasiBiaya;

use App\Filament\Concerns\ModuleGate;
use Modules\Ledger\Filament\Resources\AlokasiBiaya\Pages\ListAlokasiBiaya;
use App\Support\UiMode;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Ledger\Models\AlokasiBiaya;

/**
 * G3-23 — Riwayat alokasi biaya (cost allocation) ke divisi. Read-only; dibuat
 * lewat aksi "Buat Alokasi" pada header (AlokasiBiayaService, posting seimbang).
 */
class AlokasiBiayaResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = AlokasiBiaya::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Alokasi Biaya';

    protected static ?string $modelLabel = 'Alokasi Biaya';

    protected static ?string $pluralModelLabel = 'Alokasi Biaya';

    protected static string|\UnitEnum|null $navigationGroup = 'Akuntansi';

    protected static ?int $navigationSort = 30;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Tanggal')->dateTime()->sortable(),
                TextColumn::make('akun.nama')->label('Akun')->searchable(),
                TextColumn::make('nominal')->label('Nominal')->money('IDR'),
                TextColumn::make('driver')->label('Jumlah Divisi')
                    ->formatStateUsing(fn ($state): int => is_array($state) ? count($state) : 0)->badge(),
                TextColumn::make('periode')->label('Periode')->placeholder('—'),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (?string $s): string => $s === 'posted' ? 'success' : 'gray'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlokasiBiaya::route('/'),
        ];
    }
}
