<?php

namespace Modules\Payroll\Filament\Resources\Penugasan;

use Modules\Payroll\Filament\Resources\Penugasan\Pages\CreatePenugasanPegawai;
use Modules\Payroll\Filament\Resources\Penugasan\Pages\EditPenugasanPegawai;
use Modules\Payroll\Filament\Resources\Penugasan\Pages\ListPenugasanPegawai;
use App\Services\StorageQuotaService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Akademik\Models\Lembaga;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Jabatan;
use Modules\Payroll\Models\PenugasanPegawai;

/**
 * WP-03 — Penugasan Pegawai (jantung SDM): satu pegawai banyak jabatan di
 * banyak lembaga + cetak SK. Resource tersendiri (repo TIDAK memakai pola
 * RelationManager di mana pun) — riwayat per pegawai = filter kolom Pegawai.
 */
class PenugasanPegawaiResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = PenugasanPegawai::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Penugasan Pegawai';

    protected static ?string $modelLabel = 'Penugasan';

    protected static ?string $pluralModelLabel = 'Penugasan Pegawai';

    protected static string|\UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 26;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('payroll') && (auth()->user()?->can('run_payroll') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('run_payroll') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('run_payroll') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Penugasan')->schema([
                Select::make('employee_id')->label('Pegawai')->required()->searchable()
                    ->options(fn () => Employee::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')),
                Select::make('jabatan_id')->label('Jabatan')->required()->searchable()
                    ->options(fn () => Jabatan::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->helperText('Jabatan ber-role akses otomatis membuka menu yang sesuai untuk akun pegawai.'),
                Select::make('lembaga_id')->label('Lembaga')->searchable()->nullable()
                    ->options(fn () => Lembaga::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->helperText('Kosongkan bila penugasan lintas lembaga.'),
                Toggle::make('is_primary')->label('Jabatan Utama')->inline(false)
                    ->helperText('Hanya satu jabatan utama aktif per pegawai.'),
                DatePicker::make('tanggal_mulai')->label('Tanggal Mulai')->required()->default(now()),
                DatePicker::make('tanggal_selesai')->label('Tanggal Selesai')
                    ->helperText('Kosong = masih aktif. Mengisi tanggal menutup penugasan (role akses ikut ditinjau).')
                    ->afterOrEqual('tanggal_mulai'),
            ])->columns(2),
            Section::make('Surat Keputusan')->schema([
                TextInput::make('nomor_sk')->label('Nomor SK')->maxLength(255),
                FileUpload::make('file_sk')->label('Arsip SK (PDF/scan)')
                    ->disk(StorageQuotaService::diskName())
                    ->directory('sk-penugasan'),
                Textarea::make('keterangan')->label('Keterangan')->rows(2)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada penugasan')
            ->emptyStateDescription('Tugaskan pegawai ke jabatan (boleh rangkap, lintas lembaga).')
            ->columns([
                TextColumn::make('employee.nama')->label('Pegawai')->searchable()->sortable(),
                TextColumn::make('jabatan.nama')->label('Jabatan')->sortable(),
                TextColumn::make('lembaga.nama')->label('Lembaga')->placeholder('Lintas lembaga'),
                TextColumn::make('tanggal_mulai')->label('Mulai')->date('d M Y')->sortable(),
                TextColumn::make('tanggal_selesai')->label('Selesai')->date('d M Y')->placeholder('Aktif'),
                IconColumn::make('is_primary')->label('Utama')->boolean(),
                TextColumn::make('nomor_sk')->label('Nomor SK')->placeholder('—')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('aktif')->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Riwayat (selesai)')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('tanggal_selesai'),
                        false: fn (Builder $q) => $q->whereNotNull('tanggal_selesai'),
                    ),
            ])
            ->defaultSort('tanggal_mulai', 'desc')
            ->actions([
                Action::make('cetak_sk')
                    ->label('Cetak SK')
                    ->icon('heroicon-o-printer')
                    ->url(fn (PenugasanPegawai $r): string => route('tenant.payroll.penugasan.sk', ['penugasan' => $r->id], false))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['employee', 'jabatan', 'lembaga']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenugasanPegawai::route('/'),
            'create' => CreatePenugasanPegawai::route('/create'),
            'edit' => EditPenugasanPegawai::route('/{record}/edit'),
        ];
    }
}
