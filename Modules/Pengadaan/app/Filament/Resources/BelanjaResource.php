<?php

namespace Modules\Pengadaan\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use Modules\Pengadaan\Filament\Resources\Pages\CreateBelanja;
use Modules\Pengadaan\Filament\Resources\Pages\ListBelanja;
use App\Services\AuditLogger;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\SumberDana;
use Modules\Pengadaan\Models\Belanja;
use Modules\Pengadaan\Services\BelanjaService;

class BelanjaResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Belanja::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Belanja & Pajak';

    protected static ?string $modelLabel = 'Belanja';

    protected static ?string $pluralModelLabel = 'Belanja';

    protected static string|\UnitEnum|null $navigationGroup = 'Sarana & Logistik';

    protected static ?int $navigationSort = 8;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('pengadaan') && (auth()->user()?->canAny(['view_keuangan', 'input_belanja', 'manage_ledger']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('input_belanja') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false; // belanja terbukukan immutable; koreksi via jurnal pembalik.
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Belanja')->schema([
                DatePicker::make('tanggal')->label('Tanggal')->required()->default(now()),
                TextInput::make('uraian')->label('Uraian')->required()->maxLength(255),
                Select::make('jenis')->label('Jenis')->options([
                    'barang' => 'Barang', 'jasa' => 'Jasa', 'sewa' => 'Sewa Tanah/Bangunan',
                ])->required()->default('barang'),
                TextInput::make('kategori_rkam')->label('Kategori RKAM')->maxLength(255),
                Select::make('divisi_id')->label('Divisi/Unit')
                    ->options(fn () => DivisiUnit::orderBy('nama_divisi')->pluck('nama_divisi', 'id'))
                    ->searchable()->placeholder('—'),
                Select::make('sumber_dana_id')->label('Sumber Dana')
                    ->options(fn () => SumberDana::orderBy('kode_dana')->get()->mapWithKeys(fn ($s) => [$s->id => "{$s->kode_dana} — {$s->nama_sumber}"]))
                    ->searchable()->required(),
                TextInput::make('dpp')->label('DPP (sebelum pajak)')->numeric()->prefix('Rp')->required()->minValue(1),
                Toggle::make('vendor_npwp')->label('Vendor ber-NPWP'),
            ])->columns(2),

            Section::make('Pembukuan')->schema([
                Select::make('akun_beban_id')->label('Akun Beban/Aset (Debit)')
                    ->options(fn () => Akun::whereIn('tipe', ['BEBAN', 'ASET'])->where('is_postable', true)->orderBy('kode')
                        ->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->kode} — {$a->nama}"]))
                    ->searchable()->required(),
                Select::make('akun_kas_id')->label('Akun Kas/Bank (Kredit)')
                    ->options(fn () => Akun::kasBank()->where('is_postable', true)->orderBy('kode')
                        ->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->kode} — {$a->nama}"]))
                    ->searchable()->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('uraian')->label('Uraian')->limit(30)->searchable(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('dpp')->label('DPP')->money('IDR'),
                TextColumn::make('ppn_dipungut')->label('PPN')->money('IDR'),
                TextColumn::make('pph_dipungut')->label('PPh')->money('IDR'),
                TextColumn::make('total')->label('Total')->money('IDR'),
                TextColumn::make('status_approval')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'diajukan' => 'warning',
                        'disetujui', 'dicairkan' => 'success',
                        'ditolak' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('status_approval')->options([
                    'diajukan' => 'Diajukan', 'dicairkan' => 'Dicairkan', 'ditolak' => 'Ditolak',
                ]),
                SelectFilter::make('jenis')->options([
                    'barang' => 'Barang', 'jasa' => 'Jasa', 'sewa' => 'Sewa',
                ]),
            ])
            ->actions([
                Action::make('setujui')
                    ->label('Setujui & Cairkan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Belanja akan disetujui, dijurnal otomatis (beban, pajak, kas), dan ditandai dicairkan.')
                    ->visible(fn (Belanja $record): bool => $record->status_approval === 'diajukan' && (auth()->user()?->can('approve_pencairan') ?? false))
                    ->action(function (Belanja $record): void {
                        $hasil = app(BelanjaService::class)->setujui($record, auth()->id());
                        app(AuditLogger::class)->log(
                            action: 'pengadaan.belanja_disetujui',
                            user: auth()->user(),
                            subject: $hasil,
                            metadata: ['uraian' => $hasil->uraian, 'total' => $hasil->total],
                        );
                        Notification::make()->title('Belanja disetujui & dijurnal')->success()->send();
                    }),
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Belanja $record): bool => $record->status_approval === 'diajukan' && (auth()->user()?->can('approve_pencairan') ?? false))
                    ->action(function (Belanja $record): void {
                        app(BelanjaService::class)->tolak($record, auth()->id());
                        Notification::make()->title('Belanja ditolak')->warning()->send();
                    }),
                Action::make('cetak_bkk')
                    ->label('Cetak BKK')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Belanja $record): string => route('tenant.pengadaan.bkk.print', ['belanja' => $record->id], false))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBelanja::route('/'),
            'create' => CreateBelanja::route('/create'),
        ];
    }
}
