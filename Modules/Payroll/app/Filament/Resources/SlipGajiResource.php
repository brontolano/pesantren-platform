<?php

namespace Modules\Payroll\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\UiMode;
use Filament\Actions\Action;
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
use Modules\Payroll\Filament\Resources\Pages\CreateSlipGaji;
use Modules\Payroll\Filament\Resources\Pages\ListSlipGaji;
use Modules\Payroll\Models\SlipGaji;
use Modules\Payroll\Services\PayrollService;

class SlipGajiResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = SlipGaji::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Slip Gaji';

    protected static ?string $modelLabel = 'Slip Gaji';

    protected static ?string $pluralModelLabel = 'Slip Gaji';

    protected static string|\UnitEnum|null $navigationGroup = 'Penggajian';

    protected static ?int $navigationSort = 9;

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
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Slip Gaji (Honor Khidmah)')->schema([
                Select::make('users_id')->label('PTK / Asatidz')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()->required(),
                TextInput::make('periode')->label('Periode')->placeholder('2026-06')->required()->maxLength(20),
                TextInput::make('honor_pokok')->label('Honor Pokok')->numeric()->prefix('Rp')->default(0),
                TextInput::make('tunjangan')->label('Tunjangan')->numeric()->prefix('Rp')->default(0),
                TextInput::make('poin')->label('Poin (KPI/Khidmah)')->numeric()->default(0),
                TextInput::make('honor_output')->label('Honor Output (kegiatan)')->numeric()->prefix('Rp')->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ptk.name')->label('PTK')->searchable(),
                TextColumn::make('periode')->label('Periode')->sortable(),
                TextColumn::make('bruto')->label('Bruto')->money('IDR'),
                TextColumn::make('pph21_dipotong')->label('PPh21')->money('IDR'),
                TextColumn::make('neto')->label('Neto')->money('IDR'),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (?string $state): string => $state === 'final' ? 'success' : 'gray'),
            ])
            ->defaultSort('periode', 'desc')
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'final' => 'Final']),
            ])
            ->actions([
                Action::make('finalisasi')
                    ->label('Finalisasi & Jurnal')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SlipGaji $r): bool => $r->status === 'draft' && (auth()->user()?->can('run_payroll') ?? false))
                    ->action(function (SlipGaji $r): void {
                        $slip = app(PayrollService::class)->finalisasi($r, auth()->id());
                        app(AuditLogger::class)->log('payroll.slip_final', auth()->user(), $slip, ['periode' => $slip->periode]);
                        Notification::make()->title('Slip difinalisasi & dijurnal')->success()->send();
                    }),
                Action::make('cetak')
                    ->label('Cetak Slip')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (SlipGaji $r): string => route('tenant.payroll.slip.print', ['slip' => $r->id], false))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlipGaji::route('/'),
            'create' => CreateSlipGaji::route('/create'),
        ];
    }
}
