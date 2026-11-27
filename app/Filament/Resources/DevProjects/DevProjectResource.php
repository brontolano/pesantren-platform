<?php

namespace App\Filament\Resources\DevProjects;

use App\Filament\Resources\DevProjects\Pages\CreateDevProject;
use App\Filament\Resources\DevProjects\Pages\EditDevProject;
use App\Filament\Resources\DevProjects\Pages\ListDevProjects;
use App\Models\DevProject;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class DevProjectResource extends Resource
{
    protected static ?string $model = DevProject::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Proyek';

    protected static ?string $modelLabel = 'Proyek';

    protected static ?string $pluralModelLabel = 'Proyek';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Proyek';

    protected static ?int $navigationSort = 1;

    /** Hanya panel platform (central) + super_admin. */
    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && (auth()->user()?->isPlatformSuperAdmin() ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama Proyek')
                ->required()
                ->maxLength(255)
                ->helperText('Slug dibuat otomatis dari nama.'),
            Textarea::make('description')
                ->label('Deskripsi')
                ->rows(3)
                ->columnSpanFull(),
            Select::make('status')
                ->label('Status')
                ->options([
                    'planning' => '📋 Perencanaan',
                    'in_progress' => '🚧 Dikerjakan',
                    'on_hold' => '⏸️ Ditahan',
                    'done' => '✅ Selesai',
                ])
                ->default('planning')
                ->required(),
            Select::make('color')
                ->label('Warna Label')
                ->options([
                    'primary' => 'Hijau (primary)',
                    'info' => 'Biru',
                    'warning' => 'Kuning',
                    'danger' => 'Merah',
                    'gray' => 'Abu-abu',
                ])
                ->default('gray'),
            TextInput::make('icon')
                ->label('Ikon (heroicon, opsional)')
                ->placeholder('heroicon-o-academic-cap')
                ->maxLength(100),
            TextInput::make('sort')
                ->label('Urutan')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Proyek')->searchable()->sortable()
                    ->description(fn (DevProject $r): ?string => $r->description ? Str::limit($r->description, 60) : null),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'planning' => 'Perencanaan',
                        'in_progress' => 'Dikerjakan',
                        'on_hold' => 'Ditahan',
                        'done' => 'Selesai',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'done' => 'success',
                        'in_progress' => 'info',
                        'on_hold' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('tasks_count')->label('Task')
                    ->counts('tasks')->alignCenter(),
                TextColumn::make('progress')->label('Progres')
                    ->state(fn (DevProject $r): string => $r->progressPercent().'%')
                    ->badge()
                    ->color(fn (DevProject $r): string => match (true) {
                        $r->progressPercent() >= 100 => 'success',
                        $r->progressPercent() >= 50 => 'info',
                        $r->progressPercent() > 0 => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('updated_at')->label('Diubah')->since()->sortable(),
            ])
            ->defaultSort('sort')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevProjects::route('/'),
            'create' => CreateDevProject::route('/create'),
            'edit' => EditDevProject::route('/{record}/edit'),
        ];
    }
}
