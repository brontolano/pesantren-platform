<?php

namespace Modules\Kegiatan\Filament\Resources\Recurring;

use App\Filament\Concerns\ModuleGate;
use Modules\Kegiatan\Filament\Resources\Recurring\Pages\CreateKegiatanRecurring;
use Modules\Kegiatan\Filament\Resources\Recurring\Pages\EditKegiatanRecurring;
use Modules\Kegiatan\Filament\Resources\Recurring\Pages\ListKegiatanRecurring;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use Modules\Kegiatan\Models\KegiatanRecurring;
use Modules\Kegiatan\Models\KegiatanTemplate;
use Modules\Kegiatan\Services\KegiatanLanjutService;

class KegiatanRecurringResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = KegiatanRecurring::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Kegiatan Berulang';

    protected static ?string $modelLabel = 'Jadwal Berulang';

    protected static ?string $pluralModelLabel = 'Kegiatan Berulang';

    protected static string|\UnitEnum|null $navigationGroup = 'Kegiatan & Perizinan';

    protected static ?int $navigationSort = 6;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('kegiatan') && (auth()->user()?->can('view_santri') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Jadwal Berulang')->schema([
                TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                Select::make('template_id')->label('Template (opsional)')->native(false)->searchable()
                    ->options(fn (): array => KegiatanTemplate::query()->where('is_active', true)->orderBy('nama')
                        ->get()->mapWithKeys(fn (KegiatanTemplate $t): array => [$t->id => $t->nama])->all()),
                Select::make('frekuensi')->label('Frekuensi')->native(false)->options(KegiatanRecurring::FREKUENSI)->default('mingguan')->required(),
                TextInput::make('interval')->label('Tiap (N minggu/bulan)')->numeric()->default(1)->minValue(1),
                DatePicker::make('mulai')->label('Mulai')->required(),
                DatePicker::make('akhir')->label('Akhir (opsional)'),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('frekuensi')->label('Frekuensi')->badge()
                    ->formatStateUsing(fn (?string $s): string => KegiatanRecurring::FREKUENSI[$s] ?? $s),
                TextColumn::make('interval')->label('Interval')->alignCenter(),
                TextColumn::make('mulai')->label('Mulai')->date(),
                TextColumn::make('akhir')->label('Akhir')->date()->placeholder('—'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Action::make('generate')
                    ->label('Generate Instance')
                    ->icon('heroicon-o-bolt')
                    ->color('info')
                    ->schema([
                        DatePicker::make('sampai')->label('Generate s/d tanggal')->required()->default(now()->addMonths(3)),
                    ])
                    ->action(function (array $data, KegiatanRecurring $record): void {
                        $n = app(KegiatanLanjutService::class)->generateHingga($record, $data['sampai']);
                        Notification::make()
                            ->title($n > 0 ? "$n instance kegiatan dibuat" : 'Tidak ada instance baru (sudah ada / idempoten)')
                            ->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKegiatanRecurring::route('/'),
            'create' => CreateKegiatanRecurring::route('/create'),
            'edit' => EditKegiatanRecurring::route('/{record}/edit'),
        ];
    }
}
