<?php

namespace Modules\PPDB\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\PPDB\Filament\Resources\GelombangPpdb\Pages\CreateGelombangPpdb;
use Modules\PPDB\Filament\Resources\GelombangPpdb\Pages\EditGelombangPpdb;
use Modules\PPDB\Filament\Resources\GelombangPpdb\Pages\ListGelombangPpdb;
use Modules\PPDB\Models\GelombangPpdb;

/**
 * gap-130 — Master Gelombang PPDB terstruktur.
 *
 * Resource CRUD untuk referensi gelombang (additif). TIDAK di-wire paksa ke
 * alur registrasi publik. Slug eksplisit `gelombang-ppdb`.
 *
 * NON-UANG: `biaya_pendaftaran` hanya nominal konfigurasi referensi —
 * disimpan apa adanya, tanpa posting ke jurnal/tagihan.
 */
class GelombangPpdbResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = GelombangPpdb::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static \UnitEnum|string|null $navigationGroup = 'Pendaftaran';

    protected static ?string $slug = 'gelombang-ppdb';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Gelombang PPDB';

    protected static ?string $modelLabel = 'Gelombang PPDB';

    protected static ?string $pluralModelLabel = 'Gelombang PPDB';

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ppdb') && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ppdb')
            && (Auth::user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Gelombang')
                ->description('Master referensi gelombang PPDB. Nominal biaya di sini hanya konfigurasi tampilan — tidak diposting ke keuangan.')
                ->schema([
                    TextInput::make('nama')
                        ->label('Nama Gelombang')
                        ->placeholder('Contoh: Gelombang 1')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('tahun_ajaran')
                        ->label('Tahun Ajaran')
                        ->required()
                        ->maxLength(20)
                        ->default(fn (): string => static::defaultTahunAjaran())
                        ->helperText('Default mengikuti periode akademik aktif bila ada.'),
                    DatePicker::make('tanggal_buka')
                        ->label('Tanggal Buka')
                        ->native(false),
                    DatePicker::make('tanggal_tutup')
                        ->label('Tanggal Tutup')
                        ->native(false)
                        ->after('tanggal_buka'),
                    TextInput::make('kuota')
                        ->label('Kuota')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Kosongkan bila tanpa batas kuota.'),
                    TextInput::make('biaya_pendaftaran')
                        ->label('Biaya Pendaftaran')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp')
                        ->helperText('Nominal referensi konfigurasi saja — tidak memengaruhi pembukuan/tagihan.'),
                    Toggle::make('aktif')
                        ->label('Aktif')
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada gelombang')
            ->emptyStateDescription('Tambahkan gelombang PPDB sebagai master referensi tahun ajaran ini.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tahun_ajaran')
                    ->label('Tahun Ajaran')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tanggal_buka')
                    ->label('Buka')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('tanggal_tutup')
                    ->label('Tutup')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('kuota')
                    ->label('Kuota')
                    ->numeric()
                    ->placeholder('Tanpa batas')
                    ->sortable(),
                TextColumn::make('biaya_pendaftaran')
                    ->label('Biaya')
                    ->money('IDR')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('aktif')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('tahun_ajaran', 'desc')
            ->filters([
                TernaryFilter::make('aktif')
                    ->label('Status Aktif'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGelombangPpdb::route('/'),
            'create' => CreateGelombangPpdb::route('/create'),
            'edit' => EditGelombangPpdb::route('/{record}/edit'),
        ];
    }

    /**
     * Tahun ajaran default: ikuti PeriodeAkademik aktif bila ada (pola sama
     * dengan PengaturanBranding), fallback '2026/2027'. Dibungkus try/catch
     * agar resource tetap aman bila modul Akademik belum ter-migrasi.
     */
    protected static function defaultTahunAjaran(): string
    {
        try {
            $active = PeriodeAkademik::query()
                ->where('is_active', true)
                ->value('tahun_akademik');

            if (is_string($active) && trim($active) !== '') {
                return $active;
            }
        } catch (\Throwable) {
            // abaikan — fallback default
        }

        return '2026/2027';
    }
}
