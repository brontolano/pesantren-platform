<?php

namespace App\Filament\Resources\Mutu;

use App\Filament\Resources\Mutu\Pages\CreateDokumenMutu;
use App\Filament\Resources\Mutu\Pages\EditDokumenMutu;
use App\Filament\Resources\Mutu\Pages\ListDokumenMutu;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Mutu\Models\DokumenMutu;

class DokumenMutuResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = DokumenMutu::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Dosir Mutu (SYAMIL)';

    protected static ?string $modelLabel = 'Dokumen Mutu';

    protected static ?string $pluralModelLabel = 'Dokumen Mutu';

    protected static string|\UnitEnum|null $navigationGroup = 'Mutu / SYAMIL';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('mutu') && (auth()->user()?->can('manage_mutu') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dokumen Mutu (SPMI/SPME)')->schema([
                TextInput::make('judul')->label('Judul')->required()->maxLength(255),
                Select::make('jenis')->label('Jenis')->options([
                    'SOP' => 'SOP', 'Hasil SKM' => 'Hasil SKM', 'MoU' => 'MoU', 'SK' => 'SK Tim', 'Rubrik' => 'Rubrik',
                ])->required(),
                TextInput::make('regulasi_acuan')->label('Regulasi Acuan')->placeholder('KMA 939/2024 / KMA 1495/2025')->maxLength(255),
                TextInput::make('berkas_url')->label('Berkas (URL/path)')->maxLength(255),
                Select::make('status_kelengkapan')->label('Kelengkapan')->options([
                    'lengkap' => 'Lengkap', 'kurang' => 'Belum/ Kurang',
                ])->default('kurang')->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')->label('Judul')->searchable(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('regulasi_acuan')->label('Regulasi')->placeholder('—'),
                TextColumn::make('status_kelengkapan')->label('Kelengkapan')->badge()
                    ->color(fn (string $s): string => $s === 'lengkap' ? 'success' : 'warning')
                    ->formatStateUsing(fn (string $s): string => $s === 'lengkap' ? 'Lengkap' : 'Belum tersedia'),
            ])
            ->defaultSort('jenis')
            ->filters([
                SelectFilter::make('status_kelengkapan')->options(['lengkap' => 'Lengkap', 'kurang' => 'Belum']),
            ])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDokumenMutu::route('/'),
            'create' => CreateDokumenMutu::route('/create'),
            'edit' => EditDokumenMutu::route('/{record}/edit'),
        ];
    }
}
