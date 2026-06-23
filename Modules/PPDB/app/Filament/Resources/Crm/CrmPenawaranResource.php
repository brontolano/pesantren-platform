<?php

namespace Modules\PPDB\Filament\Resources\Crm;

use App\Filament\Concerns\ModuleGate;
use Modules\PPDB\Filament\Resources\Crm\Pages\ListCrmPenawaran;
use App\Support\UiMode;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\PPDB\Models\CrmPenawaran;

/**
 * G3-20 — Penawaran biaya (quotation) dari SkemaBiaya. Read-only; dibuat lewat
 * aksi "Buat Penawaran" pada header (CrmService).
 */
class CrmPenawaranResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = CrmPenawaran::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationLabel = 'Penawaran (Quotation)';

    protected static ?string $modelLabel = 'Penawaran';

    protected static ?string $pluralModelLabel = 'Penawaran (Quotation)';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan';

    protected static ?int $navigationSort = 30;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('crm') && (auth()->user()?->canAny(['view_laporan_pimpinan', 'manage_keuangan']) ?? false);
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
                TextColumn::make('nama_prospek')->label('Prospek')->placeholder('—')->searchable(),
                TextColumn::make('tahun_ajaran')->label('Tahun')->badge(),
                TextColumn::make('items')->label('Rincian')
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? collect($state)->map(fn ($i): string => ($i['jenis'] ?? '').' (Rp '.number_format((float) ($i['nominal'] ?? 0), 0, ',', '.').')')->implode(', ')
                        : '—')->wrap(),
                TextColumn::make('total')->label('Total')->money('IDR'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmPenawaran::route('/'),
        ];
    }
}
