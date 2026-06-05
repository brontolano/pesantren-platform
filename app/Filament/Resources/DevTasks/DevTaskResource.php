<?php

namespace App\Filament\Resources\DevTasks;

use App\Filament\Resources\DevTasks\Pages\CreateDevTask;
use App\Filament\Resources\DevTasks\Pages\EditDevTask;
use App\Filament\Resources\DevTasks\Pages\ListDevTasks;
use App\Models\DevTask;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DevTaskResource extends Resource
{
    protected static ?string $model = DevTask::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Task';

    protected static ?string $modelLabel = 'Task';

    protected static ?string $pluralModelLabel = 'Task';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Proyek';

    protected static ?int $navigationSort = 2;

    /** Hanya panel platform (central) + super_admin. */
    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && (auth()->user()?->hasRole('super_admin') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('dev_project_id')
                ->label('Proyek')
                ->relationship('project', 'name')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('title')
                ->label('Judul Task')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make('phase')
                ->label('Fase')
                ->placeholder('Fase 1 — Pendaftaran & Onboarding')
                ->maxLength(255),
            Textarea::make('detail')
                ->label('Detail / Acceptance')
                ->rows(3)
                ->columnSpanFull(),
            Textarea::make('current_state')
                ->label('Kondisi Saat Ini (gap)')
                ->rows(2)
                ->columnSpanFull(),
            Select::make('status')
                ->label('Status')
                ->options([
                    'todo' => '⬜ Belum',
                    'in_progress' => '🚧 Dikerjakan',
                    'blocked' => '⛔ Terblokir',
                    'done' => '✅ Selesai',
                ])
                ->default('todo')
                ->required()
                ->live()
                ->afterStateUpdated(function (string $state, callable $set): void {
                    if ($state === 'done') {
                        $set('progress', 100);
                    }
                }),
            Select::make('priority')
                ->label('Prioritas')
                ->options([
                    'low' => 'Rendah',
                    'medium' => 'Sedang',
                    'high' => 'Tinggi',
                    'critical' => 'Kritis',
                ])
                ->default('medium')
                ->required(),
            TextInput::make('progress')
                ->label('Progres (%)')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->default(0)
                ->suffix('%'),
            TextInput::make('effort')
                ->label('Estimasi')
                ->placeholder('S / M / L')
                ->maxLength(50),
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
                TextColumn::make('project.name')->label('Proyek')->badge()->color('gray')->sortable(),
                TextColumn::make('phase')->label('Fase')->toggleable()->wrap()->color('gray'),
                TextColumn::make('title')->label('Task')->searchable()->wrap()
                    ->description(fn (DevTask $r): ?string => $r->current_state ? '↳ '.\Illuminate\Support\Str::limit($r->current_state, 80) : null),
                SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'todo' => '⬜ Belum',
                        'in_progress' => '🚧 Dikerjakan',
                        'blocked' => '⛔ Terblokir',
                        'done' => '✅ Selesai',
                    ])
                    ->selectablePlaceholder(false),
                TextColumn::make('priority')->label('Prioritas')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'critical' => 'Kritis',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger', 'high' => 'warning', 'medium' => 'info', default => 'gray',
                    }),
                TextColumn::make('progress')->label('Progres')
                    ->formatStateUsing(fn (int $state): string => $state.'%')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 100 => 'success', $state >= 50 => 'info', $state > 0 => 'warning', default => 'gray',
                    })
                    ->sortable(),
            ])
            ->defaultSort('sort')
            ->groups([
                \Filament\Tables\Grouping\Group::make('project.name')->label('Proyek'),
                \Filament\Tables\Grouping\Group::make('phase')->label('Fase'),
            ])
            ->defaultGroup('project.name')
            ->filters([
                SelectFilter::make('dev_project_id')->label('Proyek')->relationship('project', 'name'),
                SelectFilter::make('status')->options([
                    'todo' => 'Belum', 'in_progress' => 'Dikerjakan', 'blocked' => 'Terblokir', 'done' => 'Selesai',
                ]),
                SelectFilter::make('priority')->options([
                    'low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'critical' => 'Kritis',
                ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevTasks::route('/'),
            'create' => CreateDevTask::route('/create'),
            'edit' => EditDevTask::route('/{record}/edit'),
        ];
    }
}
