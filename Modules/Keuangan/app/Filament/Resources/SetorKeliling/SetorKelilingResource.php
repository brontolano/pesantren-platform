<?php

namespace Modules\Keuangan\Filament\Resources\SetorKeliling;

use Modules\Keuangan\Filament\Resources\SetorKeliling\Pages\CreateSetorKeliling;
use Modules\Keuangan\Filament\Resources\SetorKeliling\Pages\EditSetorKeliling;
use Modules\Keuangan\Filament\Resources\SetorKeliling\Pages\ListSetorKeliling;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\SetorKelilingSesi;
use Modules\Keuangan\Services\SetorKelilingService;

/**
 * Setor Keliling — petugas mencatat setoran tabungan di lapangan (draf, layar
 * ramah HP), bendahara menyetujui → saldo terposting via TabunganService.
 */
class SetorKelilingResource extends Resource
{
    protected static ?string $model = SetorKelilingSesi::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Setor Keliling';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $modelLabel = 'Sesi Setor Keliling';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return ($user?->can('operate_pos') || $user?->can('manage_keuangan')) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sesi')->columns(2)->schema([
                TextInput::make('lokasi')->label('Lokasi keliling')
                    ->placeholder('mis. Asrama Putra')->maxLength(120),
                TextInput::make('tanggal')->label('Tanggal')->type('date')
                    ->default(now()->toDateString())->required(),
            ]),
            Section::make('Setoran Santri')->schema([
                Repeater::make('items')
                    ->relationship()
                    ->label('')
                    ->disabled(fn (?SetorKelilingSesi $record): bool => $record !== null && $record->status !== 'draf')
                    ->schema([
                        Select::make('santri_id')->label('Santri')
                            ->options(fn () => Santri::query()->where('status', 'aktif')
                                ->orderBy('nama')->limit(500)->pluck('nama', 'id'))
                            ->searchable()->required(),
                        TextInput::make('nominal')->label('Nominal')->numeric()
                            ->minValue(1)->required()->prefix('Rp'),
                        TextInput::make('catatan')->label('Catatan')->maxLength(120),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah setoran')
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                        $data['tenant_id'] = tenant('id');

                        return $data;
                    }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y'),
                TextColumn::make('lokasi')->label('Lokasi')->placeholder('—'),
                TextColumn::make('petugas.name')->label('Petugas'),
                TextColumn::make('items_count')->counts('items')->label('Jml Santri'),
                TextColumn::make('total')->label('Total')->money('IDR', locale: 'id'),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draf' => 'gray', 'diajukan' => 'warning',
                        'disetujui' => 'success', default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draf' => 'Draf', 'diajukan' => 'Diajukan',
                    'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('ajukan')
                    ->label('Ajukan')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (SetorKelilingSesi $record): bool => $record->status === 'draf')
                    ->action(function (SetorKelilingSesi $record): void {
                        try {
                            app(SetorKelilingService::class)->ajukan($record);
                            Notification::make()->title('Sesi diajukan — menunggu persetujuan bendahara.')->success()->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (SetorKelilingSesi $record): string => 'Saldo '.$record->items()->count().' santri akan bertambah total Rp '.number_format((float) $record->total, 0, ',', '.').'. Pastikan uang fisik sudah diterima.')
                    ->visible(fn (SetorKelilingSesi $record): bool => $record->status === 'diajukan' && (Auth::user()?->can('manage_keuangan') ?? false))
                    ->action(function (SetorKelilingSesi $record): void {
                        try {
                            app(SetorKelilingService::class)->setujui($record, (int) Auth::id());
                            Notification::make()->title('Disetujui — saldo terposting & wali dinotifikasi.')->success()->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([
                        Textarea::make('alasan')->label('Alasan penolakan')->required()->rows(2),
                    ])
                    ->visible(fn (SetorKelilingSesi $record): bool => $record->status === 'diajukan' && (Auth::user()?->can('manage_keuangan') ?? false))
                    ->action(function (SetorKelilingSesi $record, array $data): void {
                        try {
                            app(SetorKelilingService::class)->tolak($record, (int) Auth::id(), (string) $data['alasan']);
                            Notification::make()->title('Sesi ditolak — tidak ada saldo yang berubah.')->warning()->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('petugas');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSetorKeliling::route('/'),
            'create' => CreateSetorKeliling::route('/create'),
            'edit' => EditSetorKeliling::route('/{record}/edit'),
        ];
    }
}
