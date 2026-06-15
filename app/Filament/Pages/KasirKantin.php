<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Services\KantinService;
use Modules\Keuangan\Services\TabunganService;
use Throwable;

/**
 * Kasir Kantin (2b) — belanja kantin dipotong dari saldo tabungan santri.
 *
 * Alur: scan/ketik NIS (keyboard-wedge, pola pos gerbang WP-29) → tampil
 * santri + saldo → input nominal → santri masukkan PIN App Santri →
 * KantinService::belanja (verifikasi PIN + lock saldo + batas harian +
 * auto-jurnal idempoten). Refund (sekali saja) butuh manage_keuangan.
 */
class KasirKantin extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Kasir Kantin';

    protected static ?string $title = 'Kasir Kantin (Tabungan)';

    protected static string|\UnitEnum|null $navigationGroup = 'Kas & Kantin';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'keuangan/kasir-kantin';

    protected string $view = 'filament.pages.kasir-kantin';

    /** Input NIS (scan QR kartu santri = keyboard wedge). */
    public string $nis = '';

    /** Santri hasil scan. */
    public ?int $santriId = null;

    public string $nominal = '';

    public string $keterangan = '';

    public string $pin = '';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('keuangan')
            && (Auth::user()?->can('operate_pos') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function santri(): ?Santri
    {
        return $this->santriId ? Santri::query()->find($this->santriId) : null;
    }

    public function saldo(): float
    {
        $santri = $this->santri();

        return $santri ? app(TabunganService::class)->getSaldo($santri) : 0.0;
    }

    /** Cari santri dari NIS (enter pada input scan). */
    public function cariSantri(): void
    {
        $nis = trim($this->nis);
        if ($nis === '') {
            return;
        }

        $santri = Santri::query()->where('nis', $nis)->first();
        if ($santri === null) {
            $this->reset(['santriId', 'pin']);
            Notification::make()->title('Santri tidak ditemukan untuk NIS '.$nis.'.')->warning()->send();

            return;
        }

        $this->santriId = $santri->id;
        $this->pin = '';
    }

    public function batal(): void
    {
        $this->reset(['nis', 'santriId', 'nominal', 'keterangan', 'pin']);
    }

    /** Proses belanja: PIN diverifikasi + saldo dipotong di KantinService. */
    public function belanja(): void
    {
        $santri = $this->santri();
        if ($santri === null) {
            Notification::make()->title('Scan NIS santri terlebih dahulu.')->warning()->send();

            return;
        }

        $nominal = (float) $this->nominal;
        if ($nominal <= 0) {
            Notification::make()->title('Nominal belanja harus lebih dari 0.')->warning()->send();

            return;
        }

        try {
            $trx = app(KantinService::class)->belanja($santri, $nominal, $this->pin, [
                'keterangan' => trim($this->keterangan) !== '' ? trim($this->keterangan) : null,
                'created_by' => Auth::id(),
            ]);

            $this->reset(['nominal', 'keterangan', 'pin']);

            Notification::make()
                ->title('Belanja Rp '.number_format((float) $trx->nominal, 0, ',', '.').' tercatat. Sisa saldo Rp '.number_format((float) $trx->saldo_sesudah, 0, ',', '.').'.')
                ->success()->send();
        } catch (Throwable $e) {
            $this->pin = '';
            Notification::make()->title('Transaksi ditolak')->body($e->getMessage())->danger()->send();
        }
    }

    /** Transaksi kantin hari ini oleh kasir login. */
    public function transaksiHariIni(): Collection
    {
        return TabunganTransaksi::query()
            ->whereIn('tipe', ['belanja_kantin', 'refund_kantin'])
            ->where('created_by', Auth::id())
            ->whereDate('tanggal', now()->toDateString())
            ->with('tabungan.santri')
            ->latest('id')
            ->limit(30)
            ->get();
    }

    public function canRefund(): bool
    {
        return Auth::user()?->can('manage_keuangan') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refund')
                ->label('Refund Transaksi')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (): bool => $this->canRefund())
                ->schema([
                    TextInput::make('transaksi_id')
                        ->label('ID Transaksi Belanja')
                        ->numeric()->required()
                        ->helperText('Nomor transaksi belanja kantin yang akan dikembalikan (refund penuh, sekali saja).'),
                    Textarea::make('alasan')->label('Alasan')->rows(2),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    try {
                        $asal = TabunganTransaksi::query()->findOrFail((int) $data['transaksi_id']);
                        $trx = app(KantinService::class)->refund($asal, Auth::id(), $data['alasan'] ?? null);

                        Notification::make()
                            ->title('Refund Rp '.number_format((float) $trx->nominal, 0, ',', '.').' berhasil (transaksi #'.$asal->id.').')
                            ->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Refund gagal')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
