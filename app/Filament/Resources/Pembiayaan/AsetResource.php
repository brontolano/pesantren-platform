<?php

namespace App\Filament\Resources\Pembiayaan;

use App\Filament\Resources\Pembiayaan\Pages\CreateAset;
use App\Filament\Resources\Pembiayaan\Pages\EditAset;
use App\Filament\Resources\Pembiayaan\Pages\ListAset;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Pembiayaan\Models\Aset;
use Modules\Pembiayaan\Services\AsetMovementService;
use Modules\Pembiayaan\Services\DepresiasiService;

class AsetResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Aset::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Aset';

    protected static ?string $modelLabel = 'Aset';

    protected static ?string $pluralModelLabel = 'Aset';

    protected static string|\UnitEnum|null $navigationGroup = 'Pembiayaan';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('pembiayaan') && (auth()->user()?->can('manage_pembiayaan') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Aset')->schema([
                TextInput::make('nama_aset')->label('Nama Aset')->required()->maxLength(255),
                TextInput::make('jenis')->label('Jenis')->placeholder('Tanah / Bangunan / Kendaraan')->maxLength(255),
                TextInput::make('nilai')->label('Nilai Buku Saat Ini (Rp)')->numeric()->prefix('Rp')->default(0)
                    ->helperText('Diperbarui otomatis saat posting penyusutan.'),
                TextInput::make('nomor_sertifikat')->label('Nomor Sertifikat')->maxLength(255),
                Toggle::make('is_wakaf')->label('Aset Wakaf (terkunci dari agunan & penyusutan)')->live()
                    ->helperText('UU 41/2004 Ps 40 — aset wakaf tidak dapat dijadikan agunan dan tidak disusutkan.'),
            ])->columns(2),

            Section::make('Penyusutan & Lokasi')->schema([
                DatePicker::make('tanggal_perolehan')->label('Tanggal Perolehan'),
                TextInput::make('nilai_perolehan')->label('Nilai Perolehan (Rp)')->numeric()->prefix('Rp')->default(0)
                    ->required(fn (Get $get): bool => ($get('metode_penyusutan') ?? 'none') !== 'none')
                    ->minValue(0)
                    ->helperText('Harga perolehan historis = basis penyusutan. Bila kosong, otomatis diisi dari nilai awal.'),
                TextInput::make('lokasi')->label('Lokasi')->maxLength(255),
                TextInput::make('kategori')->label('Kategori / Kelompok')->maxLength(255),
                Select::make('metode_penyusutan')->label('Metode Penyusutan')->native(false)->live()
                    ->options(Aset::METODE)->default('none')
                    ->disabled(fn (Get $get): bool => (bool) $get('is_wakaf'))
                    ->helperText('Aset wakaf otomatis tanpa penyusutan.'),
                TextInput::make('nilai_residu')->label('Nilai Residu (Rp)')->numeric()->prefix('Rp')->default(0),
                TextInput::make('umur_manfaat_bulan')->label('Umur Manfaat (bulan)')->numeric()->minValue(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_aset')->label('Nama')->searchable(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('lokasi')->label('Lokasi')->placeholder('—')->toggleable(),
                TextColumn::make('nilai_perolehan')->label('Perolehan')->money('IDR')->toggleable(),
                TextColumn::make('akumulasi_penyusutan')->label('Akm. Penyusutan')->money('IDR')->toggleable(),
                TextColumn::make('nilai')->label('Nilai Buku')->money('IDR'),
                TextColumn::make('metode_penyusutan')->label('Metode')
                    ->formatStateUsing(fn (?string $state): string => Aset::METODE[$state] ?? '—')->badge(),
                IconColumn::make('is_wakaf')->label('Wakaf')->boolean(),
                TextColumn::make('status_aset')->label('Status')->badge()
                    ->color(fn (?string $state): string => $state === 'dilepas' ? 'danger' : 'success'),
            ])
            ->defaultSort('nama_aset')
            ->actions([
                EditAction::make(),
                Action::make('posting_penyusutan')
                    ->label('Posting Penyusutan')
                    ->icon('heroicon-o-calculator')
                    ->visible(fn (Aset $record): bool => $record->bolehDisusutkan() && $record->status_aset !== 'dilepas')
                    ->schema([
                        TextInput::make('periode')->label('Posting s/d Periode (YYYY-MM)')
                            ->default(now()->format('Y-m'))->required()
                            ->rule('regex:/^\d{4}-\d{2}$/'),
                    ])
                    ->action(function (Aset $record, array $data): void {
                        $svc = app(DepresiasiService::class);
                        $svc->buatJadwal($record);
                        $n = $svc->postingHinggaPeriode($record, $data['periode'], Auth::id());
                        \Filament\Notifications\Notification::make()
                            ->title($n > 0 ? "$n periode penyusutan diposting." : 'Tidak ada periode jatuh tempo.')
                            ->success()->send();
                    }),
                Action::make('disposal')
                    ->label('Pelepasan')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('danger')
                    ->visible(fn (Aset $record): bool => $record->status_aset !== 'dilepas')
                    ->schema([
                        TextInput::make('harga_jual')->label('Harga Jual / Pelepasan (Rp)')
                            ->numeric()->prefix('Rp')->default(0),
                        Textarea::make('keterangan')->label('Keterangan')->rows(2),
                    ])
                    ->action(function (Aset $record, array $data): void {
                        app(AsetMovementService::class)->disposal($record, [
                            'harga_jual' => (float) ($data['harga_jual'] ?? 0),
                            'keterangan' => $data['keterangan'] ?? null,
                            'created_by' => Auth::id(),
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->title($record->is_wakaf
                                ? 'Aset wakaf dilepas (tanpa pengakuan laba/rugi).'
                                : 'Aset dilepas — jurnal laba/rugi diposting.')
                            ->success()->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAset::route('/'),
            'create' => CreateAset::route('/create'),
            'edit' => EditAset::route('/{record}/edit'),
        ];
    }
}
