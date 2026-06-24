<?php

namespace Modules\Ledger\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\JurnalService;
use Throwable;

/**
 * Pencatatan jurnal manual (mode lanjutan). Tetap menulis lewat JurnalService::postJurnal
 * agar semua pagar (balance, idempoten, periode, kehalalan) ditegakkan — tidak menyentuh
 * tabel jurnal secara langsung.
 */
class JurnalManual extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = 'Jurnal Manual';

    protected static ?string $title = 'Catat Jurnal Manual';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'ledger/jurnal-manual';

    protected string $view = 'filament.pages.jurnal-manual';

    public ?array $data = [];

    /**
     * Token idempotensi per-render form (anti double-submit). Stabil selama satu
     * sesi pengisian → double-click / refresh / retry memakai token sama sehingga
     * postJurnal men-dedup jadi SATU jurnal. Diperbarui tiap form di-reset (mount)
     * → jurnal yang sengaja identik di sesi berikutnya tetap bisa diposting.
     */
    public string $idempotencyToken = '';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('ledger')
            && (Auth::user()?->can('manage_ledger') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::navAdvanced('ledger') && static::canAccess();
    }

    public function mount(): void
    {
        // Token baru tiap form di-reset → submit ulang form yang SAMA dedup,
        // tapi jurnal baru (mount ulang setelah sukses) tetap unik.
        $this->idempotencyToken = (string) Str::uuid();

        $this->form->fill([
            'tanggal' => now()->toDateString(),
            'baris' => [
                ['akun_id' => null, 'divisi_id' => null, 'sumber_dana_id' => null, 'debit' => 0, 'kredit' => 0],
                ['akun_id' => null, 'divisi_id' => null, 'sumber_dana_id' => null, 'debit' => 0, 'kredit' => 0],
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Header Jurnal')->schema([
                    DatePicker::make('tanggal')->label('Tanggal')->required()->default(now()),
                    TextInput::make('deskripsi')->label('Deskripsi')->required()->maxLength(255),
                ])->columns(2),

                Section::make('Baris Jurnal (Σdebit harus = Σkredit)')->schema([
                    Repeater::make('baris')
                        ->label('')
                        ->schema([
                            Select::make('akun_id')->label('Akun')
                                ->options(fn () => Akun::where('is_postable', true)->orderBy('kode')
                                    ->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->kode} — {$a->nama}"]))
                                ->searchable()->required(),
                            Select::make('divisi_id')->label('Divisi')
                                ->options(fn () => DivisiUnit::orderBy('nama_divisi')->pluck('nama_divisi', 'id'))
                                ->searchable()->placeholder('—'),
                            Select::make('sumber_dana_id')->label('Sumber Dana')
                                ->options(fn () => SumberDana::orderBy('kode_dana')->pluck('nama_sumber', 'id'))
                                ->searchable()->placeholder('—'),
                            TextInput::make('debit')->label('Debit')->numeric()->default(0)->minValue(0)->prefix('Rp'),
                            TextInput::make('kredit')->label('Kredit')->numeric()->default(0)->minValue(0)->prefix('Rp'),
                        ])
                        ->columns(5)
                        ->minItems(2)
                        ->addActionLabel('Tambah baris')
                        ->defaultItems(2),
                ]),
            ]);
    }

    public function simpan(): void
    {
        $data = $this->form->getState();

        // Pertahanan: token tak boleh kosong (mount selalu mengisinya, tapi jaga-jaga)
        // — ref_id kosong akan membuat key 'jurnal_manual:' yang sama untuk SEMUA jurnal
        // manual → salah-dedup (jurnal berbeda hilang). Pastikan selalu ada token unik.
        if ($this->idempotencyToken === '') {
            $this->idempotencyToken = (string) Str::uuid();
        }

        try {
            $jurnal = app(JurnalService::class)->postJurnal([
                'tanggal' => $data['tanggal'] ?? now(),
                'deskripsi' => $data['deskripsi'] ?? null,
                'tipe_sumber' => 'manual',
                // Idempotensi anti double-submit: token per-render form (lihat properti).
                'ref_type' => 'jurnal_manual',
                'ref_id' => $this->idempotencyToken,
                'dibuat_oleh' => Auth::id(),
                'baris' => array_map(fn ($b) => [
                    'akun_id' => $b['akun_id'],
                    'divisi_id' => $b['divisi_id'] ?? null,
                    'sumber_dana_id' => $b['sumber_dana_id'] ?? null,
                    'debit' => (float) ($b['debit'] ?? 0),
                    'kredit' => (float) ($b['kredit'] ?? 0),
                ], $data['baris'] ?? []),
            ]);

            // G1-01 (non-blocking): catat jejak persetujuan bila ada ApprovalRule
            // wajib (field 'total_debit') terpicu. Idempoten & no-op tanpa rule —
            // jurnal tetap terposting (tak memblokir jalur posting).
            $jurnal->ajukanPersetujuan(Auth::id());

            Notification::make()->title('Jurnal tersimpan: '.$jurnal->nomor)->success()->send();
            $this->mount(); // reset form
        } catch (Throwable $e) {
            Notification::make()->title('Gagal menyimpan jurnal')->body($e->getMessage())->danger()->send();
        }
    }
}
