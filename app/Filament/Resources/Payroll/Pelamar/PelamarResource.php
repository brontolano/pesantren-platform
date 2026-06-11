<?php

namespace App\Filament\Resources\Payroll\Pelamar;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Payroll\Pelamar\Pages\CreatePelamar;
use App\Filament\Resources\Payroll\Pelamar\Pages\EditPelamar;
use App\Filament\Resources\Payroll\Pelamar\Pages\ListPelamar;
use App\Services\StorageQuotaService;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Models\Pelamar;
use Modules\Payroll\Services\RekrutmenService;

/**
 * WP-19 — Rekrutmen Ringan (SDM/Payroll).
 *
 * - Tahap dikelola HANYA via aksi "Naikkan Tahap" (transisi divalidasi
 *   RekrutmenService) — bukan edit bebas.
 * - "Jadikan Pegawai": hanya tahap diterima, idempoten (employee_id terisi
 *   → aksi hilang); TIDAK auto-create penugasan, hanya hint ke WP-03.
 * - Berkas: acceptedFileTypes WAJIB pdf/jpg/png (pelajaran WP-08), dir
 *   'pelamar' masuk $direktoriSensitif routes/tenant.php (wajib login).
 * - Pelamar ditolak terarsip — tetap di tabel, filter tahap/Arsip Ditolak.
 */
class PelamarResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Pelamar::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Rekrutmen Pelamar';

    protected static ?string $modelLabel = 'Pelamar';

    protected static ?string $pluralModelLabel = 'Pelamar';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 27;

    /** Tipe berkas lamaran yang diizinkan — JANGAN dilonggarkan (WP-08). */
    public const ACCEPTED_FILE_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('payroll') && (auth()->user()?->can('run_payroll') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('run_payroll') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Pelamar')->schema([
                TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                TextInput::make('kontak')->label('Kontak (HP/WA/Email)')->maxLength(255),
                TextInput::make('asal')->label('Asal (daerah/lembaga)')->maxLength(255),
                Toggle::make('is_alumni')->label('Alumni pesantren ini')->inline(false),
                // Tahap dikelola via aksi "Naikkan Tahap" — edit bebas merusak
                // semantik pipeline (lompat tahap / mundur dari final).
                Select::make('tahap')->label('Tahap')->native(false)
                    ->options(Pelamar::TAHAP)->default('lamaran')
                    ->disabled()
                    ->dehydrated(fn (?Pelamar $record): bool => $record === null),
                FileUpload::make('berkas_path')
                    ->label('Berkas Lamaran (PDF/JPG/PNG)')
                    ->disk(StorageQuotaService::diskName())
                    ->directory('pelamar')
                    ->acceptedFileTypes(self::ACCEPTED_FILE_TYPES) // WAJIB — WP-08
                    ->maxSize(5120)
                    ->helperText('Maks 5 MB. CV/ijazah/sertifikat dalam satu berkas.')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('kontak')->label('Kontak')->placeholder('—')->searchable(),
                TextColumn::make('asal')->label('Asal')->placeholder('—')->toggleable(),
                IconColumn::make('is_alumni')->label('Alumni')->boolean(),
                TextColumn::make('tahap')->label('Tahap')->badge()
                    ->formatStateUsing(fn (string $state): string => Pelamar::TAHAP[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'diterima' => 'success', 'ditolak' => 'danger',
                        'lamaran' => 'gray', default => 'info',
                    }),
                TextColumn::make('berkas_path')->label('Berkas')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Lihat' : '—')
                    // POLA: serve via /_tenant-storage (Storage::url 404 di Docker)
                    ->url(fn (Pelamar $r): ?string => $r->berkas_path ? url('/_tenant-storage/'.ltrim($r->berkas_path, '/')) : null, true),
                TextColumn::make('employee.nama')->label('Pegawai')->placeholder('—')
                    ->description(fn (Pelamar $r): ?string => $r->sudahDikonversi() ? 'Sudah dikonversi' : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('tahap')->label('Tahap')->options(Pelamar::TAHAP),
                Filter::make('arsip_ditolak')->label('Arsip Ditolak')
                    ->query(fn (Builder $query): Builder => $query->where('tahap', 'ditolak')),
            ])
            ->actions([
                EditAction::make(),
                Action::make('naikkan_tahap')->label('Naikkan Tahap')
                    ->icon('heroicon-o-arrow-up-circle')->color('info')
                    ->visible(fn (Pelamar $r): bool => $r->tahapTujuanValid() !== []
                        && (auth()->user()?->can('run_payroll') ?? false))
                    ->schema(fn (Pelamar $r): array => [
                        Select::make('tahap_baru')->label('Tahap Tujuan')->required()->native(false)
                            ->options(collect($r->tahapTujuanValid())
                                ->mapWithKeys(fn (string $t) => [$t => Pelamar::TAHAP[$t] ?? $t])->all()),
                        Textarea::make('catatan')->label('Catatan Penilai (tahap ini)')->rows(3)
                            ->helperText('Tersimpan per tahap di arsip catatan penilai.'),
                    ])
                    ->action(function (Pelamar $r, array $data): void {
                        try {
                            app(RekrutmenService::class)->naikkanTahap($r, $data['tahap_baru'], $data['catatan'] ?? null);
                            Notification::make()->title('Tahap diperbarui')
                                ->body('Pelamar kini di tahap: '.(Pelamar::TAHAP[$data['tahap_baru']] ?? $data['tahap_baru']))
                                ->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Transisi tidak sah')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('jadikan_pegawai')->label('Jadikan Pegawai')
                    ->icon('heroicon-o-identification')->color('success')
                    ->visible(fn (Pelamar $r): bool => $r->tahap === 'diterima'
                        && ! $r->sudahDikonversi()
                        && (auth()->user()?->can('run_payroll') ?? false))
                    ->requiresConfirmation()
                    ->modalDescription('Membuat data Pegawai baru dari pelamar ini. Penugasan TIDAK dibuat otomatis — setelah ini, buat penugasan via menu Penugasan Pegawai (WP-03).')
                    ->action(function (Pelamar $r): void {
                        try {
                            $employee = app(RekrutmenService::class)->jadikanPegawai($r);
                            Notification::make()->title('Pegawai dibuat')
                                ->body("'{$employee->nama}' kini terdaftar sebagai pegawai. Lengkapi data & buat penugasan via menu Penugasan Pegawai (WP-03).")
                                ->success()->persistent()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal konversi')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPelamar::route('/'),
            'create' => CreatePelamar::route('/create'),
            'edit' => EditPelamar::route('/{record}/edit'),
        ];
    }
}
