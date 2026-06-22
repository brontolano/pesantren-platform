<?php

namespace Modules\Notifikasi\Filament\Resources\BroadcastWa;

use App\Support\AlbaKidsTenant;
use Modules\Notifikasi\Filament\Resources\BroadcastWa\Pages\CreateBroadcastWa;
use Modules\Notifikasi\Filament\Resources\BroadcastWa\Pages\EditBroadcastWa;
use Modules\Notifikasi\Filament\Resources\BroadcastWa\Pages\ListBroadcastWa;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Notifikasi\Models\TemplateNotifikasi;
use Modules\Notifikasi\Services\BroadcastWaService;

class BroadcastWaResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = BroadcastWa::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('notifikasi') && (auth()->user()?->canAny(['view_notifikasi', 'manage_notifikasi']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('notifikasi') && (auth()->user()?->canAny(['view_notifikasi', 'manage_notifikasi']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_notifikasi') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_notifikasi') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Broadcast')->schema([
                Select::make('template_id')
                    ->label('Template')
                    ->options(fn () => TemplateNotifikasi::query()
                        ->where('channel', 'wa')
                        ->where('is_active', true)
                        ->when(AlbaKidsTenant::isActive(), fn ($query) => $query->where('kode_template', 'like', 'albakids_%'))
                        ->pluck('judul', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('target_group')
                    ->label('Target Group')
                    ->options([
                        'wali' => 'Wali Santri',
                        'santri' => 'Santri',
                        'staff' => 'Staff',
                        'custom' => 'Custom Manual',
                    ])
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'queued' => 'Queued',
                        'processing' => 'Processing',
                        'sent' => 'Sent',
                        'partial_failed' => 'Partial Failed',
                        'failed' => 'Failed',
                    ])
                    ->default('draft')
                    ->required(),
                DateTimePicker::make('tanggal_kirim')
                    ->label('Jadwal Kirim')
                    ->seconds(false),
                KeyValue::make('filter_criteria')
                    ->label('Filter / Target')
                    ->keyLabel('Kunci')
                    ->valueLabel('Nilai')
                    ->helperText('Contoh: kelas_id=1, role=guru, target manual diisi pada key targets.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template.judul')
                    ->label('Template')
                    ->searchable(),
                TextColumn::make('target_group')
                    ->label('Target')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('attempt_count')
                    ->label('Attempt'),
                TextColumn::make('tanggal_kirim')
                    ->label('Kirim')
                    ->dateTime('d M Y H:i'),
            ])
            ->actions([
                Action::make('queue')
                    ->label('Queue')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (BroadcastWa $record): bool => auth()->user()?->can('manage_notifikasi') && in_array($record->status, ['draft', 'failed', 'partial_failed'], true))
                    ->action(function (BroadcastWa $record): void {
                        app(BroadcastWaService::class)->queue($record);
                    }),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (BroadcastWa $record): bool => auth()->user()?->can('manage_notifikasi') && in_array($record->status, ['failed', 'partial_failed'], true))
                    ->action(function (BroadcastWa $record): void {
                        app(BroadcastWaService::class)->retryFailed($record);
                    }),
                EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('template');

        if (AlbaKidsTenant::isActive()) {
            $query->whereHas('template', fn (Builder $builder) => $builder->where('kode_template', 'like', 'albakids_%'));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBroadcastWa::route('/'),
            'create' => CreateBroadcastWa::route('/create'),
            'edit' => EditBroadcastWa::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return AlbaKidsTenant::isActive() ? 'Broadcast Pesan' : 'Broadcast WA';
    }

    public static function getModelLabel(): string
    {
        return AlbaKidsTenant::isActive() ? 'Broadcast Pesan Alba Kids' : 'Broadcast WA';
    }

    public static function getPluralModelLabel(): string
    {
        return AlbaKidsTenant::isActive() ? 'Broadcast Pesan' : 'Broadcast WA';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AlbaKidsTenant::isActive() ? 'Notifikasi Custom' : 'Kelembagaan & Komunikasi';
    }
}
