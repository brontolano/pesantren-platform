<?php

namespace Modules\Keuangan\Filament\Resources\Pos\PosProfil;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
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
use Modules\Keuangan\Filament\Resources\Pos\PosProfil\Pages\CreatePosProfil;
use Modules\Keuangan\Filament\Resources\Pos\PosProfil\Pages\EditPosProfil;
use Modules\Keuangan\Filament\Resources\Pos\PosProfil\Pages\ListPosProfil;
use Modules\Keuangan\Models\PosProfil;
use Modules\Ledger\Models\Akun;

class PosProfilResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = PosProfil::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Profil Loket POS';

    protected static ?string $modelLabel = 'Profil Loket';

    protected static ?string $pluralModelLabel = 'Profil Loket POS';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan Koperasi';

    protected static ?int $navigationSort = 19;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('keuangan') && (auth()->user()?->can('operate_pos') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        $akunOptions = fn (): array => Akun::query()->where('is_kas_bank', true)->where('is_postable', true)->orderBy('kode')
            ->get()->mapWithKeys(fn (Akun $a): array => [$a->id => $a->kode.' — '.$a->nama])->all();

        return $schema->components([
            Section::make('Profil Loket')->schema([
                TextInput::make('nama')->label('Nama Loket')->required()->maxLength(255),
                TextInput::make('kode')->label('Kode')->maxLength(40),
                TextInput::make('pos_jenis')->label('Jenis/Label')->default('koperasi')->maxLength(40),
                Select::make('akun_kas_id')->label('Akun Kas (tunai)')->native(false)->searchable()->options($akunOptions),
                Select::make('akun_qris_id')->label('Akun QRIS/Bank (non-tunai)')->native(false)->searchable()->options($akunOptions),
                TextInput::make('tarif_poin')->label('Tarif Poin (Rp per 1 poin)')->numeric()->default(0)->minValue(0)
                    ->helperText('0 = loyalitas nonaktif. Poin = total belanja ÷ tarif (transparan, bukan riba).'),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Loket')->searchable(),
                TextColumn::make('pos_jenis')->label('Jenis')->badge(),
                TextColumn::make('tarif_poin')->label('Tarif Poin')
                    ->formatStateUsing(fn ($s): string => (float) $s > 0 ? 'Rp '.number_format((float) $s, 0, ',', '.') : 'nonaktif')->alignEnd(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('nama')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosProfil::route('/'),
            'create' => CreatePosProfil::route('/create'),
            'edit' => EditPosProfil::route('/{record}/edit'),
        ];
    }
}
