<?php

namespace Modules\Pembiayaan\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use Modules\Pembiayaan\Filament\Resources\Pages\CreatePembiayaan;
use Modules\Pembiayaan\Filament\Resources\Pages\ListPembiayaan;
use App\Services\AuditLogger;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Pembiayaan\Models\Aset;
use Modules\Pembiayaan\Models\PengajuanPembiayaan;
use Modules\Pembiayaan\Services\PembiayaanService;

class PembiayaanResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = PengajuanPembiayaan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Pengajuan Pembiayaan (DSCR)';

    protected static ?string $modelLabel = 'Pengajuan Pembiayaan (DSCR)';

    protected static ?string $pluralModelLabel = 'Pengajuan Pembiayaan (DSCR)';

    protected static string|\UnitEnum|null $navigationGroup = 'Pembiayaan (DSCR)';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('pembiayaan') && (auth()->user()?->can('manage_pembiayaan') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pengajuan & Simulasi DSCR')->schema([
                TextInput::make('bank_tujuan')->label('Bank Tujuan')->placeholder('BSI Mitra Edu')->maxLength(255),
                TextInput::make('produk')->label('Produk')->maxLength(255),
                TextInput::make('plafon')->label('Plafon')->numeric()->prefix('Rp')->required(),
                TextInput::make('tenor')->label('Tenor (bulan)')->numeric()->default(36),
                TextInput::make('margin')->label('Margin/Imbal Hasil Tahunan')->numeric()->prefix('Rp')->default(0),
                TextInput::make('ebitda_tahunan')->label('EBITDA Tahunan (unit komersial)')->numeric()->prefix('Rp')->default(0),
                TextInput::make('angsuran_pokok')->label('Angsuran Pokok Tahunan')->numeric()->prefix('Rp')->default(0)
                    ->helperText('DSCR = EBITDA ÷ (Angsuran Pokok + Margin). Ambang 1,25×.'),
                Select::make('aset_agunan_id')->label('Agunan (hanya non-wakaf)')
                    ->options(fn () => Aset::nonWakaf()->orderBy('nama_aset')->pluck('nama_aset', 'id'))
                    ->searchable()->placeholder('— pilih aset non-wakaf —'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bank_tujuan')->label('Bank')->searchable(),
                TextColumn::make('plafon')->label('Plafon')->money('IDR'),
                TextColumn::make('dscr')->label('DSCR')->numeric(2),
                TextColumn::make('zona')->label('Zona')->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'aman' => 'success', 'waspada' => 'warning', 'bahaya' => 'danger', default => 'gray',
                    }),
                TextColumn::make('agunan.nama_aset')->label('Agunan')->placeholder('—'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('ajukan_pembina')
                    ->label('Ajukan ke Pembina')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (PengajuanPembiayaan $r): bool => $r->status === 'draft' && $r->aset_agunan_id !== null)
                    ->action(function (PengajuanPembiayaan $r): void {
                        app(PembiayaanService::class)->mintaPersetujuanPembina($r);
                        Notification::make()->title('Diajukan ke Pembina')->success()->send();
                    }),
                Action::make('setujui_pembina')
                    ->label('Setujui (Pembina)')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (PengajuanPembiayaan $r): bool => $r->status === 'menunggu_pembina')
                    ->schema([
                        TextInput::make('esignature')->label('E-Signature Pembina (TTE)')->required(),
                    ])
                    ->action(function (PengajuanPembiayaan $r, array $data): void {
                        $hasil = app(PembiayaanService::class)->setujuiPembina($r, $data['esignature']);
                        app(AuditLogger::class)->log('pembiayaan.disetujui_pembina', auth()->user(), $hasil, ['bank' => $hasil->bank_tujuan]);
                        Notification::make()->title('Disetujui Pembina')->success()->send();
                    }),
                Action::make('cetak_paket')
                    ->label('Cetak Paket Kredit (L3)')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (PengajuanPembiayaan $r): string => route('tenant.pembiayaan.paket.print', ['pengajuan' => $r->id], false))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPembiayaan::route('/'),
            'create' => CreatePembiayaan::route('/create'),
        ];
    }
}
