<?php

namespace Modules\PPDB\Filament\Resources\UjianAttempt;

use App\Filament\Concerns\ModuleGate;
use Modules\PPDB\Filament\Resources\UjianAttempt\Pages\ListUjianAttempt;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\PPDB\Models\Soal;
use Modules\PPDB\Models\UjianAttempt;
use Modules\PPDB\Services\UjianOnlineService;

/**
 * Hasil Ujian Online — pantau attempt + NILAI MANUAL ESSAY (skor 0..bobot per
 * soal; nilai final tertimbang dihitung UjianOnlineService → HasilUjian).
 */
class UjianAttemptResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = UjianAttempt::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Hasil Ujian Online';

    protected static string|\UnitEnum|null $navigationGroup = 'Ujian Seleksi';

    protected static ?string $modelLabel = 'Attempt Ujian';

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ppdb') && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ppdb') && (Auth::user()?->can('manage_santri') ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('calonSantri.nama')->label('Peserta')->searchable(),
                TextColumn::make('paket.nama')->label('Paket'),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'berjalan' => 'warning', 'menunggu_nilai' => 'info', default => 'success',
                    }),
                TextColumn::make('nilai_pg')->label('PG'),
                TextColumn::make('nilai_essay')->label('Essay'),
                TextColumn::make('nilai_final')->label('Final')->weight('bold'),
                TextColumn::make('selesai_at')->label('Selesai')->dateTime('d M H:i'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'berjalan' => 'Berjalan', 'menunggu_nilai' => 'Menunggu Nilai', 'selesai' => 'Selesai',
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Action::make('nilai_essay')
                    ->label('Nilai Essay')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (UjianAttempt $record): bool => $record->status === 'menunggu_nilai')
                    ->schema(function (UjianAttempt $record): array {
                        $jawaban = (array) $record->jawaban;

                        return Soal::query()
                            ->whereIn('id', (array) $record->soal_tampil)
                            ->where('tipe', 'essay')
                            ->get()
                            ->map(fn (Soal $s) => TextInput::make("skor_{$s->id}")
                                ->label(mb_strimwidth($s->pertanyaan, 0, 70, '…'))
                                ->helperText('Jawaban: '.mb_strimwidth((string) ($jawaban[(string) $s->id] ?? '(kosong)'), 0, 160, '…')." — skor 0..{$s->bobot}")
                                ->numeric()->required()->minValue(0)->maxValue($s->bobot))
                            ->all();
                    })
                    ->action(function (UjianAttempt $record, array $data): void {
                        $skor = [];
                        foreach ($data as $key => $nilai) {
                            if (str_starts_with($key, 'skor_')) {
                                $skor[(int) substr($key, 5)] = (float) $nilai;
                            }
                        }
                        $final = app(UjianOnlineService::class)->nilaiEssay($record, $skor);
                        Notification::make()
                            ->title("Nilai final: {$final->nilai_final} — tercatat ke Hasil Ujian")
                            ->success()->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        // Hanya attempt PPDB (calon santri) — attempt akademik (santri_id,
        // calon_santri_id NULL) punya jalur sendiri di modul Akademik.
        return parent::getEloquentQuery()
            ->whereNotNull('calon_santri_id')
            ->with(['calonSantri', 'paket']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUjianAttempt::route('/'),
        ];
    }
}
