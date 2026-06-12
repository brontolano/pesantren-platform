<?php

namespace App\Filament\Resources\Ledger;

use App\Filament\Resources\Ledger\Pages\CreatePeriodeAkuntansi;
use App\Filament\Resources\Ledger\Pages\EditPeriodeAkuntansi;
use App\Filament\Resources\Ledger\Pages\ListPeriodeAkuntansi;
use App\Services\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Ledger\Models\PeriodeAkuntansi;
use Modules\Ledger\Services\PeriodClosingService;
use Modules\Ledger\Services\PeriodeService;

class PeriodeAkuntansiResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = PeriodeAkuntansi::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationLabel = 'Periode & Tutup Buku';

    protected static ?string $modelLabel = 'Periode Akuntansi';

    protected static ?string $pluralModelLabel = 'Periode Akuntansi';

    protected static string|\UnitEnum|null $navigationGroup = 'Akuntansi & Pajak';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Periode yang sudah closed tidak boleh diedit (immutability).
        if ($record instanceof PeriodeAkuntansi && $record->isClosed()) {
            return false;
        }

        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if ($record instanceof PeriodeAkuntansi && ($record->isClosed() || $record->jurnal()->exists())) {
            return false;
        }

        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Periode Akuntansi')->schema([
                TextInput::make('nama')->label('Nama Periode')->required()
                    ->placeholder('TA 2025/2026 — Semester Ganjil')->maxLength(255),
                DatePicker::make('tgl_mulai')->label('Tanggal Mulai')->required(),
                DatePicker::make('tgl_selesai')->label('Tanggal Selesai')->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Periode')->searchable(),
                TextColumn::make('tgl_mulai')->label('Mulai')->date('d M Y')->sortable(),
                TextColumn::make('tgl_selesai')->label('Selesai')->date('d M Y')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => $state === 'closed' ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === 'closed' ? 'Terkunci' : 'Terbuka'),
                TextColumn::make('closed_at')->label('Ditutup')->dateTime('d M Y H:i')->placeholder('—'),
            ])
            ->defaultSort('tgl_mulai', 'desc')
            ->actions([
                EditAction::make(),
                Action::make('tutup_buku')
                    ->label('Tutup Buku')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Tutup Buku Periode')
                    ->modalDescription('Memposting jurnal penutup (menol-kan pendapatan/beban ke Aset Neto) lalu mengunci periode. Setelah ditutup, tak ada transaksi bertanggal periode ini yang bisa diposting; koreksi hanya via jurnal pembalik. Lanjutkan?')
                    ->visible(fn (PeriodeAkuntansi $record): bool => ! $record->isClosed() && (auth()->user()?->can('close_period') ?? false))
                    ->action(function (PeriodeAkuntansi $record): void {
                        // G1-06: posting jurnal penutup + kunci periode.
                        $hasil = app(PeriodClosingService::class)->tutupBuku($record, auth()->id());
                        app(AuditLogger::class)->log(
                            action: 'ledger.tutup_buku',
                            user: auth()->user(),
                            subject: $record,
                            metadata: ['periode' => $record->nama, 'surplus_defisit' => $hasil['surplus_defisit']],
                        );
                        $sd = (float) $hasil['surplus_defisit'];
                        Notification::make()
                            ->title('Periode ditutup & dikunci')
                            ->body(($sd >= 0 ? 'Surplus ' : 'Defisit ').'Rp '.number_format(abs($sd), 0, ',', '.').' dipindah ke Aset Neto.')
                            ->success()->send();
                    }),
                Action::make('buka_kembali')
                    ->label('Buka Kembali')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Membuka periode memungkinkan posting kembali. Pakai hanya untuk koreksi terkendali.')
                    ->visible(fn (PeriodeAkuntansi $record): bool => $record->isClosed() && (auth()->user()?->can('close_period') ?? false))
                    ->action(function (PeriodeAkuntansi $record): void {
                        app(PeriodeService::class)->bukaKembali($record);
                        app(AuditLogger::class)->log(
                            action: 'ledger.buka_periode',
                            user: auth()->user(),
                            subject: $record,
                            metadata: ['periode' => $record->nama],
                        );
                        Notification::make()->title('Periode dibuka kembali')->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeriodeAkuntansi::route('/'),
            'create' => CreatePeriodeAkuntansi::route('/create'),
            'edit' => EditPeriodeAkuntansi::route('/{record}/edit'),
        ];
    }
}
