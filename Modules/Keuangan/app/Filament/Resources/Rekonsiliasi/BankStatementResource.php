<?php

namespace Modules\Keuangan\Filament\Resources\Rekonsiliasi;

use Modules\Keuangan\Filament\Resources\Rekonsiliasi\BankStatement\Pages\CreateBankStatement;
use Modules\Keuangan\Filament\Resources\Rekonsiliasi\BankStatement\Pages\EditBankStatement;
use Modules\Keuangan\Filament\Resources\Rekonsiliasi\BankStatement\Pages\ListBankStatement;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Keuangan\Models\BankStatement;
use Modules\Keuangan\Services\BankReconService;
use Modules\Ledger\Models\Akun;

/**
 * F6 — Rekonsiliasi Bank. Import rekening koran + auto-match ke jurnal kas/bank.
 * Gated modul keuangan + manage_keuangan (tanpa permission baru).
 */
class BankStatementResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = BankStatement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Rekonsiliasi Bank';

    protected static ?string $modelLabel = 'Rekonsiliasi Bank';

    protected static ?string $pluralModelLabel = 'Rekonsiliasi Bank';

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Keuangan';

    protected static ?int $navigationSort = 7;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_keuangan') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return ($record->status !== 'terkunci') && (auth()->user()?->can('manage_keuangan') ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return ($record->status !== 'terkunci') && (auth()->user()?->can('manage_keuangan') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Rekening Koran')->schema([
                Select::make('akun_id')->label('Akun Kas/Bank')
                    ->options(fn () => Akun::query()->where('is_kas_bank', true)->orderBy('kode')->pluck('nama', 'id'))
                    ->searchable()->required(),
                TextInput::make('nama')->label('Nama/Periode')->required()->maxLength(255),
                DatePicker::make('periode_awal')->label('Periode Awal'),
                DatePicker::make('periode_akhir')->label('Periode Akhir'),
                TextInput::make('saldo_awal')->label('Saldo Awal')->numeric()->prefix('Rp'),
                TextInput::make('saldo_akhir')->label('Saldo Akhir')->numeric()->prefix('Rp'),
                Select::make('sumber')->label('Sumber')->options([
                    'manual' => 'Input Manual', 'csv' => 'Impor CSV', 'mt940' => 'MT940',
                ])->default('manual')->native(false),
            ])->columns(2),
            Section::make('Baris Mutasi')->schema([
                Repeater::make('lines')->label('Mutasi')->relationship('lines')
                    ->schema([
                        DatePicker::make('tanggal')->required(),
                        TextInput::make('keterangan')->maxLength(255),
                        TextInput::make('referensi')->label('No. Referensi')->maxLength(255),
                        TextInput::make('nominal')->numeric()->required()->prefix('Rp'),
                        Select::make('arah')->options(['masuk' => 'Masuk (debit)', 'keluar' => 'Keluar (kredit)'])
                            ->required()->native(false),
                    ])->columns(2)->defaultItems(1)
                    ->addActionLabel('Tambah Mutasi')
                    ->disabled(fn (?BankStatement $record) => $record?->status === 'terkunci'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('akun.nama')->label('Akun')->placeholder('—'),
                TextColumn::make('periode_akhir')->label('Periode')->date('M Y')->placeholder('—'),
                TextColumn::make('lines_count')->counts('lines')->label('Baris')->badge()->color('gray'),
                TextColumn::make('cocok')->label('Cocok')->badge()->color('success')
                    ->state(fn (BankStatement $r): int => $r->lines()->where('status', 'cocok')->count()),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => $state === 'terkunci' ? 'danger' : 'warning'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'terkunci' => 'Terkunci']),
            ])
            ->actions([
                Action::make('autoMatch')->label('Auto-cocokkan')->icon('heroicon-o-bolt')->color('success')
                    ->visible(fn (BankStatement $r): bool => $r->status !== 'terkunci' && (auth()->user()?->can('manage_keuangan') ?? false))
                    ->action(function (BankStatement $r): void {
                        $ring = app(BankReconService::class)->autoMatch($r);
                        Notification::make()->title('Rekonsiliasi otomatis selesai')
                            ->body("Cocok: {$ring['cocok']} · Belum cocok: {$ring['belum_cocok']} dari {$ring['total']} baris.")
                            ->success()->send();
                    }),
                Action::make('reset')->label('Reset Pencocokan')->icon('heroicon-o-arrow-path')->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (BankStatement $r): bool => $r->status !== 'terkunci' && (auth()->user()?->can('manage_keuangan') ?? false))
                    ->action(function (BankStatement $r): void {
                        $svc = app(BankReconService::class);
                        foreach ($r->lines()->where('status', 'cocok')->get() as $line) {
                            $svc->batalkanCocok($line);
                        }
                        Notification::make()->title('Pencocokan direset')->success()->send();
                    }),
                Action::make('kunci')->label('Kunci')->icon('heroicon-o-lock-closed')->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Setelah dikunci, rekonsiliasi tidak dapat diubah lagi.')
                    ->visible(fn (BankStatement $r): bool => $r->status !== 'terkunci' && (auth()->user()?->can('manage_keuangan') ?? false))
                    ->action(function (BankStatement $r): void {
                        app(BankReconService::class)->kunci($r);
                        Notification::make()->title('Rekonsiliasi dikunci')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankStatement::route('/'),
            'create' => CreateBankStatement::route('/create'),
            'edit' => EditBankStatement::route('/{record}/edit'),
        ];
    }
}
