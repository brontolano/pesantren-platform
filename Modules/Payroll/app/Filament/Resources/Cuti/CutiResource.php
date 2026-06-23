<?php

namespace Modules\Payroll\Filament\Resources\Cuti;

use App\Filament\Concerns\ModuleGate;
use Modules\Payroll\Filament\Resources\Cuti\Pages\CreateCuti;
use Modules\Payroll\Filament\Resources\Cuti\Pages\ListCuti;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Models\Cuti;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Services\CutiService;

/**
 * G2-11 — Pengajuan & persetujuan cuti (leave). Approval lewat F1.
 */
class CutiResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Cuti::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Cuti / Leave';

    protected static ?string $modelLabel = 'Cuti';

    protected static ?string $pluralModelLabel = 'Cuti';

    protected static string|\UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 25;

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
        return false; // koreksi via ajukan ulang
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pengajuan Cuti')->schema([
                Select::make('employee_id')->label('Pegawai')->required()->searchable()
                    ->options(fn () => Employee::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')),
                Select::make('jenis')->label('Jenis')->native(false)->default('tahunan')
                    ->options(['tahunan' => 'Tahunan', 'sakit' => 'Sakit', 'penting' => 'Penting',
                        'melahirkan' => 'Melahirkan', 'lainnya' => 'Lainnya']),
                Toggle::make('is_berbayar')->label('Cuti Berbayar')
                    ->helperText('Nonaktifkan utk cuti tak berbayar (memotong gaji per hari efektif).')
                    ->default(fn (): bool => CutiService::defaultBerbayar()),
                DatePicker::make('tanggal_mulai')->label('Mulai')->required(),
                DatePicker::make('tanggal_selesai')->label('Selesai')->required(),
                Textarea::make('alasan')->label('Alasan')->rows(2)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.nama')->label('Pegawai')->searchable(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                IconColumn::make('is_berbayar')->label('Berbayar')->boolean(),
                TextColumn::make('tanggal_mulai')->label('Mulai')->date('d M Y')->sortable(),
                TextColumn::make('tanggal_selesai')->label('Selesai')->date('d M Y'),
                TextColumn::make('jumlah_hari')->label('Hari')->badge()->color('gray'),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'disetujui' => 'success', 'ditolak' => 'danger', default => 'warning',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(['diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak']),
            ])
            ->actions([
                Action::make('setujui')->label('Setujui')->icon('heroicon-o-check')->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Cuti $r): bool => $r->status === 'diajukan' && (auth()->user()?->can('run_payroll') ?? false))
                    ->action(function (Cuti $r): void {
                        try {
                            app(CutiService::class)->setujui($r, auth()->id());
                            Notification::make()->title('Cuti disetujui')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal menyetujui')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('tolak')->label('Tolak')->icon('heroicon-o-x-mark')->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Cuti $r): bool => $r->status === 'diajukan' && (auth()->user()?->can('run_payroll') ?? false))
                    ->action(function (Cuti $r): void {
                        app(CutiService::class)->tolak($r, auth()->id(), 'Ditolak admin');
                        Notification::make()->title('Cuti ditolak')->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCuti::route('/'),
            'create' => CreateCuti::route('/create'),
        ];
    }
}
