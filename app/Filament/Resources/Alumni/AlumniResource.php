<?php

namespace App\Filament\Resources\Alumni;

use App\Filament\Resources\Alumni\Pages\ListAlumni;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Kesantrian\Models\Alumni;

class AlumniResource extends Resource
{
    protected static ?string $model = Alumni::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Alumni';

    protected static ?string $modelLabel = 'Alumni';

    protected static ?string $pluralModelLabel = 'Alumni';

    protected static string|\UnitEnum|null $navigationGroup = 'Kesantrian';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // Alumni dibuat lewat proses kelulusan, bukan manual
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nis')
                    ->label('NIS')
                    ->searchable(),
                TextColumn::make('santri.nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tahun_lulus')
                    ->label('Tahun Lulus')
                    ->sortable(),
                TextColumn::make('status_lanjutan')
                    ->label('Status Lanjutan')
                    ->placeholder('-'),
                TextColumn::make('sekolah_tujuan')
                    ->label('Sekolah Tujuan')
                    ->placeholder('-'),
            ])
            ->defaultSort('tahun_lulus', 'desc')
            ->filters([
                SelectFilter::make('tahun_lulus')
                    ->options(fn () => Alumni::distinct()->pluck('tahun_lulus', 'tahun_lulus')->toArray()),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('santri');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlumni::route('/'),
        ];
    }
}
