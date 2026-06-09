<?php

namespace App\Filament\Resources\Ledger;

use App\Filament\Resources\Ledger\Anggaran\Pages\CreateAnggaran;
use App\Filament\Resources\Ledger\Anggaran\Pages\EditAnggaran;
use App\Filament\Resources\Ledger\Anggaran\Pages\ListAnggaran;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Anggaran;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\AnggaranGuardService;

/**
 * Anggaran / RKAM — pagu belanja per akun + dimensi + tahun. Dipakai guard
 * anggaran saat belanja disetujui (peringatan/blokir). Gated modul ledger + manage_ledger.
 */
class AnggaranResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Anggaran::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Anggaran (RKAM)';

    protected static ?string $modelLabel = 'Anggaran';

    protected static ?string $pluralModelLabel = 'Anggaran (RKAM)';

    protected static string|\UnitEnum|null $navigationGroup = 'Buku Besar';

    protected static ?int $navigationSort = 8;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
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
        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pagu Anggaran RKAM')
                ->description('Tetapkan pagu belanja per akun (opsional per divisi & sumber dana) untuk satu tahun. Realisasi dibandingkan otomatis saat belanja disetujui.')
                ->schema([
                    TextInput::make('tahun_anggaran')
                        ->label('Tahun Anggaran')
                        ->required()
                        ->maxLength(9)
                        ->default((string) now()->year)
                        ->helperText('Tahun kalender realisasi, mis. 2026.'),
                    Select::make('akun_id')
                        ->label('Akun (beban)')
                        ->required()
                        ->searchable()
                        ->options(fn (): array => Akun::query()->where('is_postable', true)->orderBy('kode')
                            ->get()->mapWithKeys(fn (Akun $a): array => [$a->id => $a->kode.' — '.$a->nama])->all()),
                    Select::make('divisi_id')
                        ->label('Divisi / Unit')
                        ->placeholder('— semua / tanpa divisi')
                        ->searchable()
                        ->options(fn (): array => DivisiUnit::query()->orderBy('nama_divisi')->pluck('nama_divisi', 'id')->all()),
                    Select::make('sumber_dana_id')
                        ->label('Sumber Dana')
                        ->placeholder('— semua / tanpa sumber')
                        ->searchable()
                        ->options(fn (): array => SumberDana::query()->orderBy('kode_dana')->pluck('nama_sumber', 'id')->all())
                        ->helperText('Pisahkan pagu BOS/BOP/mandiri bila perlu.'),
                    TextInput::make('nominal_anggaran')
                        ->label('Pagu (Rp)')
                        ->numeric()->required()->minValue(0)->prefix('Rp'),
                    Select::make('mode')
                        ->label('Mode')
                        ->required()->default('peringatan')
                        ->options([
                            'peringatan' => 'Peringatan (boleh lampaui, ditandai)',
                            'blokir' => 'Blokir (belanja pelampau ditolak)',
                        ]),
                    Textarea::make('keterangan')->label('Keterangan')->rows(2)->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tahun_anggaran')->label('Tahun')->sortable()->searchable(),
                TextColumn::make('akun.kode')->label('Akun')->description(fn (Anggaran $r): ?string => $r->akun?->nama)->searchable(),
                TextColumn::make('divisi.nama_divisi')->label('Divisi')->placeholder('— semua'),
                TextColumn::make('sumberDana.nama_sumber')->label('Sumber Dana')->placeholder('— semua'),
                TextColumn::make('nominal_anggaran')->label('Pagu')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.'))->alignEnd(),
                TextColumn::make('realisasi')->label('Realisasi')
                    ->state(fn (Anggaran $r): float => app(AnggaranGuardService::class)
                        ->realisasi((int) $r->akun_id, $r->divisi_id, $r->sumber_dana_id, $r->tahun_anggaran))
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.'))->alignEnd(),
                TextColumn::make('sisa')->label('Sisa')
                    ->state(fn (Anggaran $r): float => round((float) $r->nominal_anggaran - app(AnggaranGuardService::class)
                        ->realisasi((int) $r->akun_id, $r->divisi_id, $r->sumber_dana_id, $r->tahun_anggaran), 2))
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->color(fn ($state): string => (float) $state < 0 ? 'danger' : 'success')->alignEnd(),
                TextColumn::make('mode')->label('Mode')->badge()
                    ->color(fn (string $s): string => $s === 'blokir' ? 'danger' : 'warning'),
            ])
            ->defaultSort('tahun_anggaran', 'desc')
            ->filters([
                SelectFilter::make('mode')->options(['peringatan' => 'Peringatan', 'blokir' => 'Blokir']),
            ])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnggaran::route('/'),
            'create' => CreateAnggaran::route('/create'),
            'edit' => EditAnggaran::route('/{record}/edit'),
        ];
    }
}
