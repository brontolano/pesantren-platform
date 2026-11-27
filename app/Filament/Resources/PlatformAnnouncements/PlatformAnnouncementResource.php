<?php

namespace App\Filament\Resources\PlatformAnnouncements;

use App\Filament\Resources\PlatformAnnouncements\Pages\CreatePlatformAnnouncement;
use App\Filament\Resources\PlatformAnnouncements\Pages\EditPlatformAnnouncement;
use App\Filament\Resources\PlatformAnnouncements\Pages\ListPlatformAnnouncements;
use App\Models\PlatformAnnouncement;
use App\Models\Tenant;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PlatformAnnouncementResource extends Resource
{
    protected static ?string $model = PlatformAnnouncement::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Pengumuman Platform';

    protected static ?string $modelLabel = 'Pengumuman';

    protected static string|UnitEnum|null $navigationGroup = 'Komunikasi & Dukungan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Isi pengumuman')->columns(2)->schema([
                TextInput::make('title')->label('Judul')->required()->maxLength(180)->columnSpanFull(),
                Textarea::make('body')->label('Pesan untuk tenant')->rows(6)->required()->maxLength(10000)->columnSpanFull(),
                Select::make('category')->label('Jenis informasi')->options([
                    'informasi' => 'Informasi', 'teknis' => 'Informasi teknis', 'pembaruan' => 'Pembaruan layanan',
                ])->required()->default('informasi'),
                Select::make('priority')->label('Tingkat perhatian')->options([
                    'normal' => 'Biasa', 'penting' => 'Penting', 'mendesak' => 'Mendesak',
                ])->required()->default('normal'),
            ]),
            Section::make('Penerima dan waktu')->columns(2)->schema([
                Select::make('target_scope')->label('Penerima')->options([
                    'all' => 'Semua pesantren', 'tenant' => 'Satu pesantren',
                ])->live()->required()->default('all')
                    ->afterStateUpdated(fn ($state, callable $set) => $state === 'all' ? $set('tenant_id', null) : null),
                Select::make('tenant_id')->label('Pesantren')->options(fn () => Tenant::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()->exists('tenants', 'id')->required(fn (callable $get): bool => $get('target_scope') === 'tenant')
                    ->visible(fn (callable $get): bool => $get('target_scope') === 'tenant'),
                Select::make('status')->label('Status')->options(['draft' => 'Draf', 'published' => 'Terbit'])
                    ->required()->default('draft'),
                DateTimePicker::make('published_at')->label('Mulai ditampilkan')->seconds(false)
                    ->required(fn (callable $get): bool => $get('status') === 'published')
                    ->helperText('Pengumuman berstatus Terbit akan muncul mulai waktu ini.'),
                DateTimePicker::make('ends_at')->label('Akhir penayangan')->seconds(false)
                    ->after('published_at')->nullable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Judul')->searchable()->limit(48),
            TextColumn::make('target_scope')->label('Penerima')->badge()
                ->formatStateUsing(fn (string $state, PlatformAnnouncement $record): string => $state === 'all' ? 'Semua pesantren' : ($record->tenant?->name ?? 'Satu pesantren')),
            TextColumn::make('category')->label('Jenis')->badge(),
            TextColumn::make('priority')->label('Perhatian')->badge(),
            TextColumn::make('status')->label('Status')->badge(),
            TextColumn::make('published_at')->label('Mulai')->dateTime('d M Y H:i')->placeholder('Belum dijadwalkan'),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Draf', 'published' => 'Terbit']),
            SelectFilter::make('category')->options(['informasi' => 'Informasi', 'teknis' => 'Informasi teknis', 'pembaruan' => 'Pembaruan layanan']),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformAnnouncements::route('/'),
            'create' => CreatePlatformAnnouncement::route('/create'),
            'edit' => EditPlatformAnnouncement::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isPlatformSuperAdmin() ?? false;
    }
}
