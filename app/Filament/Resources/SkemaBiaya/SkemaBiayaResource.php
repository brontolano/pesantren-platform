<?php

namespace App\Filament\Resources\SkemaBiaya;

use App\Filament\Resources\SkemaBiaya\Pages\CreateSkemaBiaya;
use App\Filament\Resources\SkemaBiaya\Pages\EditSkemaBiaya;
use App\Filament\Resources\SkemaBiaya\Pages\ListSkemaBiaya;
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
use Modules\Keuangan\Models\SkemaBiaya;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\SumberDana;

/**
 * G2-16 — Skema Biaya (Pricing Rule). CRUD tarif baku per tahun/divisi/jenis.
 */
class SkemaBiayaResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = SkemaBiaya::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Skema Biaya';

    protected static ?string $modelLabel = 'Skema Biaya';

    protected static ?string $pluralModelLabel = 'Skema Biaya';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_keuangan') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_keuangan') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Skema Biaya')->schema([
                TextInput::make('tahun_ajaran')->label('Tahun Ajaran')->required()
                    ->placeholder('2026/2027')->maxLength(20),
                Select::make('divisi_id')->label('Divisi / Program')
                    ->options(fn () => DivisiUnit::query()->orderBy('nama_divisi')->pluck('nama_divisi', 'id'))
                    ->placeholder('(berlaku umum)')->searchable(),
                Select::make('jenis_tagihan')->label('Jenis Tagihan')->required()->native(false)
                    ->options([
                        'spp' => 'SPP / Syahriyah', 'daftar_ulang' => 'Daftar Ulang', 'seragam' => 'Seragam',
                        'kitab' => 'Kitab', 'kegiatan' => 'Kegiatan', 'lainnya' => 'Lainnya',
                    ]),
                TextInput::make('nominal')->label('Nominal (Rp)')->numeric()->required()->prefix('Rp'),
                Select::make('sumber_dana_id')->label('Dana Penanggung')
                    ->options(fn () => SumberDana::query()->orderBy('kode_dana')->pluck('nama_sumber', 'id'))
                    ->placeholder('(default)')->searchable(),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
                Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tahun_ajaran')->label('Tahun')->sortable()->searchable(),
                TextColumn::make('divisi.nama_divisi')->label('Divisi')->placeholder('Umum'),
                TextColumn::make('jenis_tagihan')->label('Jenis')->badge(),
                TextColumn::make('nominal')->label('Nominal')->money('IDR')->sortable(),
                TextColumn::make('sumberDana.nama_sumber')->label('Dana')->placeholder('—')->toggleable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->defaultSort('tahun_ajaran', 'desc')
            ->filters([
                SelectFilter::make('jenis_tagihan')->options([
                    'spp' => 'SPP', 'daftar_ulang' => 'Daftar Ulang', 'seragam' => 'Seragam',
                    'kitab' => 'Kitab', 'kegiatan' => 'Kegiatan', 'lainnya' => 'Lainnya',
                ]),
            ])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSkemaBiaya::route('/'),
            'create' => CreateSkemaBiaya::route('/create'),
            'edit' => EditSkemaBiaya::route('/{record}/edit'),
        ];
    }
}
