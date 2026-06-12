<?php

namespace App\Filament\Resources\Mutu;

use App\Filament\Resources\Mutu\MutuInspeksi\Pages\CreateMutuInspeksi;
use App\Filament\Resources\Mutu\MutuInspeksi\Pages\EditMutuInspeksi;
use App\Filament\Resources\Mutu\MutuInspeksi\Pages\ListMutuInspeksi;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Mutu\Models\MutuInspeksi;
use Modules\Mutu\Models\MutuSasaran;

class MutuInspeksiResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = MutuInspeksi::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Inspeksi / Audit Mutu';

    protected static ?string $modelLabel = 'Inspeksi Mutu';

    protected static ?string $pluralModelLabel = 'Inspeksi / Audit Mutu';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan & Mutu';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('mutu') && (auth()->user()?->can('manage_mutu') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Inspeksi / Audit')->schema([
                TextInput::make('judul')->label('Judul')->required()->maxLength(255),
                Select::make('area')->label('Area')->native(false)->options(MutuSasaran::AREA)->default('umum'),
                DatePicker::make('tanggal')->label('Tanggal'),
                TextInput::make('auditor')->label('Auditor')->maxLength(255),
                Select::make('status')->label('Status')->native(false)
                    ->options(['draft' => 'Draft', 'selesai' => 'Selesai'])->default('draft'),
                Textarea::make('ringkasan')->label('Ringkasan')->rows(3)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')->label('Judul')->searchable()->wrap(),
                TextColumn::make('area')->label('Area')->badge()
                    ->formatStateUsing(fn (?string $s): string => MutuSasaran::AREA[$s] ?? $s),
                TextColumn::make('tanggal')->label('Tanggal')->date()->placeholder('—'),
                TextColumn::make('auditor')->label('Auditor')->placeholder('—'),
                TextColumn::make('temuan_count')->label('Temuan')->counts('temuan')->badge(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (?string $s): string => $s === 'selesai' ? 'success' : 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMutuInspeksi::route('/'),
            'create' => CreateMutuInspeksi::route('/create'),
            'edit' => EditMutuInspeksi::route('/{record}/edit'),
        ];
    }
}
