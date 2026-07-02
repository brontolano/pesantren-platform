<?php

namespace Modules\Akademik\Filament\Resources\NgajiSesi\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Akademik\Models\NgajiSesi;
use Modules\Akademik\Models\NgajiSesiPeserta;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

/**
 * Issue #330 — Peserta sesi ngaji massal: kehadiran + nilai ringkas per
 * santri. Pakai relasi HasMany `peserta` (model NgajiSesiPeserta
 * ber-BelongsToTenant → tenant_id ter-set otomatis), BUKAN
 * BelongsToMany->attach (pivot melewati event model → tenant_id NULL) —
 * pola KeanggotaanRelationManager (Halaqah).
 */
class PesertaRelationManager extends RelationManager
{
    protected static string $relationship = 'peserta';

    protected static ?string $title = 'Peserta Sesi';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('santri_id')
                ->label('Santri')
                ->relationship('santri', 'nama')
                ->searchable()
                ->preload()
                ->required(),
            Toggle::make('hadir')
                ->label('Hadir')
                ->default(true),
            TextInput::make('nilai')
                ->label('Nilai Ringkas (0–100, opsional)')
                ->numeric()
                ->minValue(0)
                ->maxValue(100),
            TextInput::make('catatan')
                ->label('Catatan')
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('santri.nis')->label('NIS')->toggleable(),
                IconColumn::make('hadir')->label('Hadir')->boolean(),
                TextColumn::make('nilai')->label('Nilai')->placeholder('—'),
                TextColumn::make('catatan')->label('Catatan')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah Peserta'),
                Action::make('tambahSatuKelas')
                    ->label('Tambah Satu Kelas')
                    ->icon('heroicon-o-users')
                    ->color('gray')
                    ->schema([
                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->options(fn () => Kelas::query()->where('is_active', true)->orderBy('nama_kelas')->pluck('nama_kelas', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var NgajiSesi $sesi */
                        $sesi = $this->getOwnerRecord();

                        $sudah = $sesi->peserta()->pluck('santri_id')->all();

                        $baru = Santri::query()
                            ->where('kelas_id', $data['kelas_id'])
                            ->where('status', 'aktif')
                            ->whereNotIn('id', $sudah)
                            ->pluck('id');

                        // create() per baris (bukan insert massal) agar event model
                        // BelongsToTenant mengisi tenant_id.
                        foreach ($baru as $santriId) {
                            NgajiSesiPeserta::create([
                                'ngaji_sesi_id' => $sesi->id,
                                'santri_id' => $santriId,
                                'hadir' => true,
                            ]);
                        }

                        Notification::make()
                            ->title($baru->count().' peserta ditambahkan')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
