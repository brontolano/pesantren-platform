<?php

namespace Modules\Akademik\Filament\Resources\RaporDiniyah;

use App\Filament\Concerns\ModuleGate;
use Modules\Akademik\Filament\Resources\RaporDiniyah\Pages\IsiRubrik;
use Modules\Akademik\Filament\Resources\RaporDiniyah\Pages\ListRaporDiniyah;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\RaporDiniyah;
use Modules\Akademik\Services\RaporDiniyahService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

/**
 * WP-10 — Rapor Diniyah (pola RaporResource): generate per kelas + cetak,
 * plus halaman IsiRubrik (grid massal skor ubudiyah/akhlak).
 */
class RaporDiniyahResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = RaporDiniyah::class;

    protected static ?string $slug = 'akademik/rapor-diniyah';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Rapor Diniyah';

    protected static string|\UnitEnum|null $navigationGroup = 'Nilai & Rapor';

    protected static ?int $navigationSort = 6;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable(),
                TextColumn::make('santri.kelas.nama_kelas')->label('Kelas')->sortable(),
                TextColumn::make('periodeAkademik.nama')->label('Periode')->sortable(),
                TextColumn::make('blok.nilai_imtihan.rata_rata')->label('Rata Imtihan'),
                TextColumn::make('blok.ubudiyah_akhlak.agregat')->label('Ubudiyah'),
                TextColumn::make('blok.disiplin.poin_bersih')->label('Poin Disiplin'),
                TextColumn::make('generated_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->headerActions([
                Action::make('isiRubrik')
                    ->label('Isi Rubrik Ubudiyah/Akhlak')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => auth()->user()?->can('manage_akademik') ?? false)
                    ->url(fn (): string => static::getUrl('isi-rubrik')),
                Action::make('generateRaporDiniyahKelas')
                    ->label('Generate Rapor Diniyah Kelas')
                    ->icon('heroicon-o-sparkles')
                    ->visible(fn (): bool => auth()->user()?->can('manage_akademik') ?? false)
                    ->form([
                        Select::make('periode_akademik_id')
                            ->label('Periode Akademik')
                            ->options(fn () => PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                            ->required()
                            ->searchable(),
                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (array $data): void {
                        $periode = PeriodeAkademik::query()->findOrFail($data['periode_akademik_id']);
                        $service = app(RaporDiniyahService::class);

                        $jumlah = Santri::query()
                            ->where('kelas_id', $data['kelas_id'])
                            ->where('status', 'aktif')
                            ->orderBy('nama')
                            ->get()
                            ->each(fn (Santri $santri) => $service->generate($santri, $periode))
                            ->count();

                        Notification::make()
                            ->title("Rapor diniyah {$jumlah} santri ter-generate.")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('print')
                    ->label('Cetak / Simpan PDF')
                    ->icon('heroicon-o-printer')
                    ->url(fn (RaporDiniyah $record): string => route('tenant.akademik.export.rapor-diniyah.print', $record))
                    ->openUrlInNewTab(false)
                    ->visible(fn () => tenancy()->initialized),
            ])
            ->defaultSort('generated_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['santri.kelas', 'periodeAkademik']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRaporDiniyah::route('/'),
            'isi-rubrik' => IsiRubrik::route('/isi-rubrik'),
        ];
    }
}
