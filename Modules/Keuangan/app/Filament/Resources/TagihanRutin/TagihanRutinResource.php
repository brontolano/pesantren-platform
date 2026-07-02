<?php

namespace Modules\Keuangan\Filament\Resources\TagihanRutin;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Models\Kelas;
use Modules\Keuangan\Filament\Resources\TagihanRutin\Pages\CreateTagihanRutin;
use Modules\Keuangan\Filament\Resources\TagihanRutin\Pages\EditTagihanRutin;
use Modules\Keuangan\Filament\Resources\TagihanRutin\Pages\ListTagihanRutin;
use Modules\Keuangan\Models\TagihanRutin;
use Modules\Keuangan\Services\TagihanRutinService;
use Modules\Keuangan\Support\SyahriahKomponen;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\SumberDana;

/**
 * Tagihan Rutin — aturan langganan tagihan (SPP/makan/asrama) yang diterbitkan
 * OTOMATIS tiap periode oleh command keuangan:terbitkan-tagihan-rutin.
 */
class TagihanRutinResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = TagihanRutin::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Tagihan Rutin';

    protected static ?string $modelLabel = 'Tagihan Rutin';

    protected static ?string $pluralModelLabel = 'Tagihan Rutin';

    protected static string|\UnitEnum|null $navigationGroup = 'Tagihan Santri';

    protected static ?int $navigationSort = 3;

    /** @var array<string,string> Jenis tagihan baku (fleksibel — bisa diperluas). */
    public const JENIS = [
        'spp' => 'SPP / Syahriyah',
        'makan' => 'Uang Makan',
        'asrama' => 'Asrama / Kos',
        'kitab' => 'Kitab',
        'kegiatan' => 'Kegiatan',
        'infaq' => 'Infaq',
        'daftar_ulang' => 'Daftar Ulang',
        'lainnya' => 'Lainnya',
    ];

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_tagihan', 'manage_tagihan']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_tagihan') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_tagihan') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tagihan & Nominal')->schema([
                TextInput::make('nama')->label('Nama Aturan')->required()
                    ->placeholder('Mis. SPP Bulanan Banin')->maxLength(255)->columnSpanFull(),
                Select::make('jenis_tagihan')->label('Jenis Tagihan')->required()->native(false)
                    ->options(self::JENIS)
                    ->helperText('Menentukan idempotensi: 1 santri 1 tagihan jenis ini per bulan.'),
                TextInput::make('nominal')->label('Nominal (Rp)')->numeric()->required()->prefix('Rp')
                    ->helperText('Bila rincian komponen diisi, nominal dihitung otomatis dari jumlah komponen saat disimpan.'),
                Select::make('frekuensi')->label('Frekuensi')->native(false)->default('bulanan')
                    ->options(['bulanan' => 'Bulanan', 'semester' => 'Per Semester', 'tahunan' => 'Tahunan']),
                Toggle::make('aktif')->label('Aktif')->default(true)->inline(false)
                    ->helperText('Nonaktif = tidak diterbitkan otomatis.'),
            ])->columns(2),

            Section::make('Rincian Komponen (opsional)')
                ->description('Syahriah berlapis dalam satu tagihan — mis. makan, listrik, madrasah diniyah, kas kamar, tabungan, bisyaroh. Total tagihan = jumlah seluruh komponen. Komponen bertanda "Masuk Tabungan" otomatis disetorkan ke tabungan santri saat tagihan lunas.')
                ->schema([
                    static::komponenRepeater(),
                ])->collapsed(fn (?Model $record): bool => empty($record?->komponen)),

            Section::make('Untuk Siapa (Target Santri)')->schema([
                Select::make('target_tipe')->label('Target')->native(false)->required()->default('semua')
                    ->live()
                    ->options([
                        'semua' => 'Semua santri aktif',
                        'lembaga' => 'Pondok / Lembaga tertentu',
                        'kelas' => 'Kelas tertentu',
                        'tingkat' => 'Tingkat tertentu',
                    ]),
                Select::make('target_lembaga_id')->label('Pondok / Lembaga')
                    ->options(fn () => Lembaga::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->searchable()->visible(fn (Get $get): bool => $get('target_tipe') === 'lembaga')
                    ->required(fn (Get $get): bool => $get('target_tipe') === 'lembaga'),
                Select::make('target_kelas_id')->label('Kelas')
                    ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                    ->searchable()->visible(fn (Get $get): bool => $get('target_tipe') === 'kelas')
                    ->required(fn (Get $get): bool => $get('target_tipe') === 'kelas'),
                TextInput::make('target_tingkat')->label('Tingkat')->placeholder('Mis. 6')
                    ->visible(fn (Get $get): bool => $get('target_tipe') === 'tingkat')
                    ->required(fn (Get $get): bool => $get('target_tipe') === 'tingkat'),
            ])->columns(2),

            Section::make('Jadwal Penerbitan')->schema([
                TextInput::make('tanggal_terbit')->label('Tanggal Terbit')->numeric()->required()
                    ->minValue(1)->maxValue(28)->default(1)
                    ->helperText('Hari ke-berapa tiap bulan tagihan dibuat (1–28).'),
                TextInput::make('tempo_hari')->label('Jatuh Tempo (hari)')->numeric()->required()
                    ->minValue(0)->maxValue(60)->default(10)
                    ->helperText('Berapa hari setelah terbit tagihan jatuh tempo.'),
            ])->columns(2),

            Section::make('Lanjutan (opsional)')->schema([
                Select::make('sumber_dana_id')->label('Dana Penanggung')
                    ->options(fn () => SumberDana::query()->orderBy('kode_dana')->pluck('nama_sumber', 'id'))
                    ->placeholder('(default)')->searchable(),
                Select::make('divisi_unit_id')->label('Divisi / Unit')
                    ->options(fn () => DivisiUnit::query()->orderBy('nama_divisi')->pluck('nama_divisi', 'id'))
                    ->placeholder('(tidak ada)')->searchable(),
                Toggle::make('notif_wa')->label('Kirim WA ke wali saat terbit')->inline(false)->default(false),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255)->columnSpanFull(),
            ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable()->weight('bold'),
                TextColumn::make('jenis_tagihan')->label('Jenis')->badge()
                    ->formatStateUsing(fn (string $state): string => self::JENIS[$state] ?? $state),
                TextColumn::make('nominal')->label('Nominal')->money('IDR')->sortable()
                    ->description(fn (TagihanRutin $r): ?string => empty($r->komponen)
                        ? null
                        : count($r->komponen).' komponen'),
                TextColumn::make('target_tipe')->label('Target')->badge()
                    ->formatStateUsing(fn (TagihanRutin $r): string => match ($r->target_tipe) {
                        'lembaga' => 'Pondok: '.($r->lembaga?->nama ?? '—'),
                        'kelas' => 'Kelas: '.($r->kelas?->nama_kelas ?? '—'),
                        'tingkat' => 'Tingkat '.$r->target_tingkat,
                        default => 'Semua santri',
                    }),
                TextColumn::make('tanggal_terbit')->label('Terbit')
                    ->formatStateUsing(fn ($state): string => 'Tgl '.$state),
                TextColumn::make('terakhir_terbit_periode')->label('Terakhir')->placeholder('Belum pernah')->toggleable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->defaultSort('nama')
            ->filters([
                SelectFilter::make('jenis_tagihan')->options(self::JENIS),
                SelectFilter::make('aktif')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->actions([
                Action::make('terbitkan')
                    ->label('Terbitkan Sekarang')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (): bool => auth()->user()?->can('manage_tagihan') ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Terbitkan Tagihan Rutin Sekarang')
                    ->modalDescription('Tagihan dibuat untuk semua santri target pada periode bulan ini. Aman diulang — santri yang sudah punya tagihan jenis ini bulan ini akan dilewati (tidak dobel).')
                    ->action(function (TagihanRutin $record): void {
                        $s = (new TagihanRutinService)->terbitkan($record, null, true);
                        Notification::make()
                            ->title('Tagihan rutin diterbitkan')
                            ->body("Periode {$s['periode']}: {$s['dibuat']} tagihan dibuat, {$s['dilewati']} dilewati (sudah ada) dari {$s['target']} santri target.")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Repeater rincian komponen — dipakai form resource & modal template (#332).
     */
    public static function komponenRepeater(string $name = 'komponen'): Repeater
    {
        return Repeater::make($name)
            ->label('Komponen')
            ->schema([
                TextInput::make('nama')->label('Nama Komponen')->required()
                    ->placeholder('Mis. Madrasah diniyah / Kas kamar / Bisyaroh'),
                TextInput::make('nominal')->label('Nominal (Rp)')->numeric()->required()
                    ->prefix('Rp')->minValue(1),
                Toggle::make('masuk_tabungan')->label('Masuk Tabungan')->default(false)->inline(false)
                    ->helperText('Disetor ke tabungan santri saat lunas.'),
            ])
            ->columns(3)
            ->addActionLabel('Tambah Komponen')
            ->reorderable(false)
            ->default([]);
    }

    /**
     * Sinkronkan data form: normalisasi komponen; bila ada komponen,
     * nominal aturan = total komponen (jaga invarian jalur uang).
     */
    public static function sinkronkanKomponen(array $data): array
    {
        $komponen = SyahriahKomponen::normalize($data['komponen'] ?? null);
        $data['komponen'] = $komponen ?: null;

        if ($komponen !== []) {
            $data['nominal'] = SyahriahKomponen::total($komponen);
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTagihanRutin::route('/'),
            'create' => CreateTagihanRutin::route('/create'),
            'edit' => EditTagihanRutin::route('/{record}/edit'),
        ];
    }
}
