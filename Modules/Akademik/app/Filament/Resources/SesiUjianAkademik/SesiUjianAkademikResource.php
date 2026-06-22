<?php

namespace Modules\Akademik\Filament\Resources\SesiUjianAkademik;

use Modules\Akademik\Filament\Resources\SesiUjianAkademik\Pages\CreateSesiUjianAkademik;
use Modules\Akademik\Filament\Resources\SesiUjianAkademik\Pages\EditSesiUjianAkademik;
use Modules\Akademik\Filament\Resources\SesiUjianAkademik\Pages\ListSesiUjianAkademik;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\SesiUjianAkademik;
use Modules\Akademik\Services\NilaiService;
use Modules\Akademik\Services\UjianAkademikService;
use Modules\Kesantrian\Models\Kelas;
use Modules\PPDB\Models\PaketSoal;

/**
 * F4 — Jadwal Sesi Ujian per rombel + aksi "Tarik ke Nilai" (nilai final
 * attempt → komponen nilai pilihan via NilaiGridService, idempoten).
 */
class SesiUjianAkademikResource extends Resource
{
    protected static ?string $model = SesiUjianAkademik::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Sesi Ujian Online';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik & Tahfidz';

    protected static ?string $modelLabel = 'Sesi Ujian';

    protected static ?string $slug = 'akademik/sesi-ujian-akademik';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        return $user->can('manage_akademik')
            || Pelajaran::query()->where('guru_pengampu_id', $user->getKey())->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sesi')->columns(2)->schema([
                TextInput::make('nama')->label('Nama sesi')->maxLength(150)
                    ->helperText('Mis. UAS Ganjil — Matematika 7A'),
                Select::make('paket_soal_id')->label('Paket soal')
                    ->options(fn () => PaketSoal::query()->whereNotNull('pelajaran_id')
                        ->where('is_active', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $paket = $state ? PaketSoal::find($state) : null;
                        if ($paket?->pelajaran_id) {
                            $set('pelajaran_id', $paket->pelajaran_id);
                        }
                    }),
                Select::make('pelajaran_id')->label('Pelajaran')
                    ->options(fn () => Pelajaran::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->required(),
                Select::make('kelas_id')->label('Kelas (rombel)')
                    ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                    ->required(),
                Select::make('periode_akademik_id')->label('Periode akademik')
                    ->options(fn () => PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                    ->helperText('Kosong = pakai periode aktif saat tarik nilai.'),
                DateTimePicker::make('mulai_at')->label('Dibuka')->required()->seconds(false),
                DateTimePicker::make('selesai_at')->label('Ditutup')->required()->seconds(false)->after('mulai_at'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Sesi')->searchable()->placeholder('—'),
                TextColumn::make('paket.nama')->label('Paket'),
                TextColumn::make('pelajaran.nama')->label('Pelajaran')->badge(),
                TextColumn::make('kelas.nama_kelas')->label('Kelas'),
                TextColumn::make('mulai_at')->label('Dibuka')->dateTime('d M H:i'),
                TextColumn::make('selesai_at')->label('Ditutup')->dateTime('d M H:i'),
                TextColumn::make('attempts_count')->counts('attempts')->label('Attempt'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->actions([
                Action::make('tarik_nilai')
                    ->label('Tarik ke Nilai')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Select::make('komponen_id')->label('Komponen nilai tujuan (mis. UAS)')
                            ->options(fn (SesiUjianAkademik $record) => app(NilaiService::class)
                                ->komponenUntukPelajaran((int) $record->pelajaran_id)
                                ->pluck('nama', 'id'))
                            ->required(),
                    ])
                    ->action(function (SesiUjianAkademik $record, array $data) {
                        $jumlah = app(UjianAkademikService::class)
                            ->tarikKeNilai($record, (int) $data['komponen_id'], Auth::user());

                        Notification::make()
                            ->title("Nilai {$jumlah} santri ditarik ke komponen.")
                            ->success()->send();
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSesiUjianAkademik::route('/'),
            'create' => CreateSesiUjianAkademik::route('/create'),
            'edit' => EditSesiUjianAkademik::route('/{record}/edit'),
        ];
    }
}
