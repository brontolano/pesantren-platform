<?php

namespace Modules\Payroll\Filament\Resources\Pinjaman;

use App\Filament\Concerns\ModuleGate;
use Modules\Payroll\Filament\Resources\Pinjaman\Pages\CreatePinjamanPegawai;
use Modules\Payroll\Filament\Resources\Pinjaman\Pages\ListPinjamanPegawai;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PinjamanPegawai;
use Modules\Payroll\Services\KasbonService;

/**
 * WP-23 — Kasbon / pinjaman pegawai. Approval F1 (pola Cuti), pencairan &
 * pelunasan via KasbonService (posting jurnal idempoten). Filter
 * "pegawai nonaktif berutang" = surfacing pinjaman berjalan milik pegawai
 * yang sudah dideaktivasi (resign) — TIDAK auto-hapus, TIDAK blokir.
 */
class PinjamanPegawaiResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = PinjamanPegawai::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Kasbon Pegawai';

    protected static ?string $modelLabel = 'Kasbon Pegawai';

    protected static ?string $pluralModelLabel = 'Kasbon Pegawai';

    protected static string|\UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 26;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('payroll') && (auth()->user()?->can('run_payroll') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('run_payroll') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false; // uang: koreksi via batalkan + ajukan ulang, bukan edit.
    }

    public static function canDelete(Model $record): bool
    {
        return false; // jejak pinjaman tak boleh hilang.
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pengajuan Kasbon')->schema([
                Select::make('employee_id')->label('Pegawai')->required()->searchable()
                    ->options(fn () => Employee::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')),
                DatePicker::make('tanggal')->label('Tanggal')->default(now())->required(),
                TextInput::make('nominal')->label('Nominal Pinjaman (Rp)')->numeric()->required()->minValue(1),
                TextInput::make('cicilan_bulanan')->label('Cicilan per Bulan (Rp)')->numeric()->required()->minValue(1)
                    ->helperText('Dipotong otomatis dari gaji tiap bulan; cicilan terakhir otomatis sebesar sisa.'),
                Textarea::make('catatan')->label('Catatan')->rows(2)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $bolehPayroll = fn (): bool => auth()->user()?->can('run_payroll') ?? false;

        return $table
            ->columns([
                TextColumn::make('employee.nama')->label('Pegawai')->searchable()
                    ->description(fn (PinjamanPegawai $r): ?string => $r->employee && ! $r->employee->aktif ? 'NONAKTIF — masih berutang' : null),
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('nominal')->label('Pokok')->money('IDR', locale: 'id'),
                TextColumn::make('cicilan_bulanan')->label('Cicilan/bln')->money('IDR', locale: 'id'),
                TextColumn::make('sisa_pokok')->label('Sisa')->money('IDR', locale: 'id')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'lunas' => 'success', 'berjalan' => 'info', 'disetujui' => 'primary',
                        'batal' => 'danger', default => 'warning',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'berjalan' => 'Berjalan',
                    'lunas' => 'Lunas', 'batal' => 'Batal',
                ]),
                Filter::make('nonaktif_berutang')->label('Pegawai nonaktif masih berutang')
                    ->query(fn (Builder $q): Builder => $q->where('status', 'berjalan')
                        ->whereHas('employee', fn (Builder $e) => $e->where('aktif', false))),
            ])
            ->actions([
                Action::make('setujui')->label('Setujui')->icon('heroicon-o-check')->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PinjamanPegawai $r): bool => $r->status === 'diajukan' && (auth()->user()?->can('run_payroll') ?? false))
                    ->action(function (PinjamanPegawai $r): void {
                        try {
                            app(KasbonService::class)->setujui($r, auth()->id());
                            Notification::make()->title('Kasbon disetujui')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal menyetujui')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('batalkan')->label('Batalkan')->icon('heroicon-o-x-mark')->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PinjamanPegawai $r): bool => in_array($r->status, ['diajukan', 'disetujui'], true) && (auth()->user()?->can('run_payroll') ?? false))
                    ->action(function (PinjamanPegawai $r): void {
                        try {
                            app(KasbonService::class)->batalkan($r, auth()->id());
                            Notification::make()->title('Kasbon dibatalkan')->warning()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal membatalkan')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('cairkan')->label('Cairkan')->icon('heroicon-o-arrow-down-tray')->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('Posting jurnal: D Piutang Pegawai / K Kas. Hanya sekali per kasbon.')
                    ->visible(fn (PinjamanPegawai $r): bool => $r->status === 'disetujui' && (auth()->user()?->can('run_payroll') ?? false))
                    ->action(function (PinjamanPegawai $r): void {
                        try {
                            app(KasbonService::class)->cairkan($r, auth()->id());
                            Notification::make()->title('Kasbon dicairkan')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal mencairkan')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('lunasi')->label('Lunasi')->icon('heroicon-o-banknotes')->color('success')
                    ->visible(fn (PinjamanPegawai $r): bool => $r->status === 'berjalan' && (auth()->user()?->can('run_payroll') ?? false))
                    ->form(fn (PinjamanPegawai $r) => [
                        TextInput::make('nominal')->label('Nominal Pelunasan (Rp)')->numeric()->required()
                            ->minValue(1)->default((float) $r->sisa_pokok)
                            ->helperText('Maksimal sisa pokok: Rp '.number_format((float) $r->sisa_pokok, 0, ',', '.')),
                    ])
                    ->action(function (PinjamanPegawai $r, array $data): void {
                        try {
                            app(KasbonService::class)->lunasi($r, (float) $data['nominal'], auth()->id());
                            Notification::make()->title('Pelunasan dicatat')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal melunasi')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPinjamanPegawai::route('/'),
            'create' => CreatePinjamanPegawai::route('/create'),
        ];
    }
}
