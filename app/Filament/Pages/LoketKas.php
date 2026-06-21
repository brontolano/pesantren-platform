<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\PosKategori;
use Modules\Keuangan\Models\PosSesi;
use Modules\Keuangan\Services\PosKasService;
use Throwable;

/**
 * Loket Kas (kasir) — antarmuka transaksional POS tunai.
 *
 * Menulis SELALU lewat PosKasService (bukaSesi/catat/tutupSesi) agar semua
 * invarian (state-machine sesi, saldo_sistem, rekonsiliasi, routing handler,
 * posting Ledger) ditegakkan. Tidak menyentuh tabel pos_* langsung.
 *
 * Alur: Buka Sesi → Catat transaksi (routing per kategori.handler) → Tutup Sesi
 * (input saldo fisik → hitung selisih).
 */
class LoketKas extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Loket Kas';

    protected static ?string $title = 'Loket Kas (Kasir)';

    protected static string|\UnitEnum|null $navigationGroup = 'Kas';

    protected static ?int $navigationSort = 19;

    protected static ?string $slug = 'keuangan/loket-kas';

    protected string $view = 'filament.pages.loket-kas';

    /** State form transaksi. */
    public ?array $data = [];

    /** Id sesi kasir aktif (null = belum buka). */
    public ?int $sesiId = null;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('koperasi')
            && (Auth::user()?->can('operate_pos') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->sesiId = $this->currentSesi()?->id;
        $this->form->fill();
    }

    /** Sesi 'buka' milik kasir login (satu kasir satu sesi aktif). */
    public function currentSesi(): ?PosSesi
    {
        return PosSesi::query()
            ->where('kasir_id', Auth::id())
            ->where('status', 'buka')
            ->latest('dibuka_pada')
            ->first();
    }

    public function hasOpenSession(): bool
    {
        return $this->currentSesi() !== null;
    }

    /** 15 transaksi terakhir sesi aktif (untuk panel blade). */
    public function transaksiTerbaru(): Collection
    {
        $sesi = $this->currentSesi();
        if ($sesi === null) {
            return collect();
        }

        return $sesi->transaksi()
            ->with('kategori')
            ->latest('id')
            ->limit(15)
            ->get();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Catat Transaksi Tunai')
                    ->description('Pilih kategori; santri/tagihan diminta otomatis sesuai jenis transaksi.')
                    ->schema([
                        Select::make('kategori_id')
                            ->label('Kategori')
                            ->required()
                            ->live()
                            ->options(fn (): array => PosKategori::query()->aktif()->orderBy('sort')->get()
                                ->mapWithKeys(fn (PosKategori $k): array => [
                                    $k->id => $k->nama.' — '.($k->jenis === 'pemasukan' ? 'masuk' : 'keluar'),
                                ])->all())
                            ->native(false),
                        TextInput::make('nominal')
                            ->label('Nominal')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->prefix('Rp'),
                        Select::make('santri_id')
                            ->label('Santri')
                            ->options(fn (): array => Santri::query()->orderBy('nama')->limit(500)->get()
                                ->mapWithKeys(fn (Santri $s): array => [$s->id => trim(($s->nis ? $s->nis.' — ' : '').$s->nama)])->all())
                            ->searchable()
                            ->visible(fn (Get $get): bool => $this->kategoriButuhSantri($get('kategori_id')))
                            ->required(fn (Get $get): bool => $this->kategoriButuhSantri($get('kategori_id'))),
                        TextInput::make('tagihan_id')
                            ->label('ID Tagihan')
                            ->numeric()
                            ->helperText('Nomor tagihan yang akan dibayar.')
                            ->visible(fn (Get $get): bool => $this->kategoriButuhTagihan($get('kategori_id')))
                            ->required(fn (Get $get): bool => $this->kategoriButuhTagihan($get('kategori_id'))),
                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bukaSesi')
                ->label('Buka Sesi Kas')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->visible(fn (): bool => ! $this->hasOpenSession())
                ->schema([
                    TextInput::make('saldo_awal')
                        ->label('Saldo Awal (modal laci)')
                        ->numeric()->default(0)->minValue(0)->required()->prefix('Rp'),
                    Select::make('pos_jenis')
                        ->label('Jenis Loket')
                        ->required()->default('kantor_keuangan')
                        ->options([
                            'kantor_keuangan' => 'Kantor Keuangan',
                            'tabungan' => 'Tabungan Santri',
                            'daftar_ulang' => 'Daftar Ulang',
                            'koperasi' => 'Koperasi / Kantin',
                            'umum' => 'Umum',
                        ]),
                    Textarea::make('catatan')->label('Catatan')->rows(2),
                ])
                ->action(function (array $data): void {
                    $sesi = app(PosKasService::class)->bukaSesi([
                        'kasir_id' => Auth::id(),
                        'saldo_awal' => (float) ($data['saldo_awal'] ?? 0),
                        'pos_jenis' => $data['pos_jenis'] ?? 'kantor_keuangan',
                        'catatan' => $data['catatan'] ?? null,
                    ]);
                    $this->sesiId = $sesi->id;
                    $this->form->fill();

                    Notification::make()->title('Sesi kas dibuka.')->success()->send();
                }),

            Action::make('tutupSesi')
                ->label('Tutup Sesi')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn (): bool => $this->hasOpenSession())
                ->schema([
                    Placeholder::make('saldo_sistem')
                        ->label('Saldo menurut sistem')
                        ->content(fn (): string => 'Rp '.number_format((float) ($this->currentSesi()?->saldo_sistem ?? 0), 0, ',', '.')),
                    TextInput::make('saldo_fisik')
                        ->label('Saldo fisik (hitung uang di laci)')
                        ->numeric()->required()->minValue(0)->prefix('Rp'),
                ])
                ->action(function (array $data): void {
                    $sesi = $this->currentSesi();
                    if ($sesi === null) {
                        Notification::make()->title('Tidak ada sesi terbuka.')->warning()->send();

                        return;
                    }

                    $closed = app(PosKasService::class)->tutupSesi($sesi, (float) ($data['saldo_fisik'] ?? 0));
                    $this->sesiId = null;
                    $this->form->fill();

                    $selisih = (float) $closed->selisih;
                    $label = $selisih === 0.0
                        ? 'pas (tidak ada selisih)'
                        : ($selisih > 0 ? 'lebih Rp '.number_format($selisih, 0, ',', '.') : 'kurang Rp '.number_format(abs($selisih), 0, ',', '.'));

                    Notification::make()
                        ->title('Sesi ditutup — kas '.$label.'.')
                        ->{$selisih === 0.0 ? 'success' : 'warning'}()
                        ->send();
                }),
        ];
    }

    /** Simpan transaksi (dipanggil dari tombol blade). */
    public function catat(): void
    {
        $sesi = $this->currentSesi();
        if ($sesi === null) {
            Notification::make()->title('Buka sesi kas terlebih dahulu.')->warning()->send();

            return;
        }

        $data = $this->form->getState();

        try {
            app(PosKasService::class)->catat($sesi, (int) $data['kategori_id'], [
                'nominal' => (float) ($data['nominal'] ?? 0),
                'santri_id' => $data['santri_id'] ?? null,
                'tagihan_id' => $data['tagihan_id'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'dibuat_oleh' => Auth::id(),
            ]);

            $this->sesiId = $sesi->id;
            $this->form->fill();

            Notification::make()->title('Transaksi tercatat.')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Gagal mencatat transaksi')->body($e->getMessage())->danger()->send();
        }
    }

    private function kategoriButuhSantri(mixed $kategoriId): bool
    {
        return in_array($this->handlerKategori($kategoriId), ['tabungan_setor', 'tabungan_tarik'], true);
    }

    private function kategoriButuhTagihan(mixed $kategoriId): bool
    {
        return in_array($this->handlerKategori($kategoriId), ['daftar_ulang', 'spp'], true);
    }

    private function handlerKategori(mixed $kategoriId): ?string
    {
        if (empty($kategoriId)) {
            return null;
        }

        return PosKategori::query()->whereKey($kategoriId)->value('handler');
    }
}
