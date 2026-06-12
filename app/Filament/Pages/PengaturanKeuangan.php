<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Modules\Ledger\Support\FiturKeuangan;

/**
 * Pengaturan modul Keuangan per tenant — termasuk toggle pemisahan dana
 * halal/syubhat (Baitul Maal). Saat OFF, KehalalanGuard non-aktif (keuangan biasa).
 */
class PengaturanKeuangan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Pengaturan Keuangan';

    protected static ?string $title = 'Pengaturan Keuangan';

    protected static string|\UnitEnum|null $navigationGroup = 'Akuntansi & Pajak';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'keuangan/pengaturan';

    protected string $view = 'filament.pages.pengaturan-keuangan';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->canAny(['manage_tenant_settings', 'manage_ledger']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'pemisahan_kehalalan' => FiturKeuangan::pemisahanKehalalanAktif(),
            'jenis_lembaga' => Setting::getTenantStringValue('keuangan.jenis_lembaga', 'pesantren'),
            'ambang_dscr' => FiturKeuangan::ambangDscr(),
            'tarif_poin' => FiturKeuangan::tarifPoin(),
            'pph21_persen' => FiturKeuangan::pph21Persen(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Pemisahan Dana Halal & Syubhat (Baitul Maal)')
                    ->description('Bila aktif, uang syubhat/non-halal (mis. bunga bank) dipisah sebagai Dana Kebajikan — bukan pendapatan — dan dilarang untuk sarana ibadah. Klasifikasi & penyaluran wajib disahkan otoritas syar\'i pesantren.')
                    ->icon('heroicon-o-scale')
                    ->schema([
                        Toggle::make('pemisahan_kehalalan')
                            ->label('Aktifkan pemisahan dana halal & syubhat')
                            ->helperText('Jika mati: sistem berjalan sebagai keuangan biasa (penjagaan kehalalan non-aktif).'),
                    ]),

                Section::make('Jenis Lembaga (Rezim Pajak/LPJ)')
                    ->description('Menentukan template BOS Madrasah vs BOP Pesantren.')
                    ->icon('heroicon-o-building-library')
                    ->schema([
                        Select::make('jenis_lembaga')->label('Jenis Lembaga')->options([
                            'madrasah' => 'Madrasah (BOS Madrasah)',
                            'pesantren' => 'Pesantren (BOP Pesantren)',
                        ])->default('pesantren')->required(),
                    ]),

                Section::make('Parameter Perhitungan (dapat diubah saat kebijakan berganti)')
                    ->description('Nilai default; ubah di sini bila aturan berubah — tanpa perlu ngoding ulang.')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        TextInput::make('ambang_dscr')->label('Ambang DSCR (kelayakan kredit)')
                            ->numeric()->step('0.01')->default(1.25)
                            ->helperText('Default 1,25× (standar lembaga pendidikan). Aman ≥ ambang, waspada 1,0–ambang, bahaya < 1,0.'),
                        TextInput::make('tarif_poin')->label('Tarif Honor per Poin (Payroll)')
                            ->numeric()->prefix('Rp')->default(10000)
                            ->helperText('Honor poin = Poin × tarif ini.'),
                        TextInput::make('pph21_persen')->label('Tarif PPh 21 (%)')
                            ->numeric()->step('0.01')->suffix('%')->default(0)
                            ->helperText('Default 0% (banyak honor di bawah PTKP). Sesuaikan bila perlu.'),
                    ])->columns(3),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::updateOrCreate(
            ['key' => FiturKeuangan::KEY_PEMISAHAN_KEHALALAN],
            ['value' => ['enabled' => (bool) ($data['pemisahan_kehalalan'] ?? false)]],
        );
        Setting::updateOrCreate(
            ['key' => 'keuangan.jenis_lembaga'],
            ['value' => ['text' => $data['jenis_lembaga'] ?? 'pesantren']],
        );
        Setting::updateOrCreate(
            ['key' => 'keuangan.dscr.ambang'],
            ['value' => ['value' => (float) ($data['ambang_dscr'] ?? 1.25)]],
        );
        Setting::updateOrCreate(
            ['key' => 'keuangan.payroll.tarif_poin'],
            ['value' => ['value' => (float) ($data['tarif_poin'] ?? 10000)]],
        );
        Setting::updateOrCreate(
            ['key' => 'keuangan.payroll.pph21_persen'],
            ['value' => ['value' => (float) ($data['pph21_persen'] ?? 0)]],
        );

        Notification::make()->title('Pengaturan keuangan disimpan')->success()->send();
    }
}
