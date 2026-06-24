<?php

namespace Modules\CMS\Filament\Resources\Comment;

use App\Filament\Concerns\ModuleGate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\CMS\Models\Comment;

/**
 * Moderasi komentar publik (1-aplikasi, Fase 2c). Antrean staf: setujui/tolak.
 * Komentar dibuat dari halaman artikel publik — TIDAK ada form create di sini.
 * Gerbang: izin `moderasi_komentar` (admin/TU/kepala).
 */
class CommentResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Comment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Moderasi Komentar';

    protected static ?string $modelLabel = 'Komentar';

    protected static ?string $pluralModelLabel = 'Komentar';

    protected static string|\UnitEnum|null $navigationGroup = 'Website & Konten';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('cms') && (auth()->user()?->can('moderasi_komentar') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('cms') && (auth()->user()?->can('moderasi_komentar') ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('moderasi_komentar') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getEloquentQuery()->where('status', Comment::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada komentar')
            ->emptyStateDescription('Komentar pengunjung pada artikel akan muncul di sini untuk Anda tinjau.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->columns([
                TextColumn::make('author_name')
                    ->label('Penulis')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('body')
                    ->label('Komentar')
                    ->wrap()
                    ->limit(120)
                    ->searchable(),
                TextColumn::make('article.title')
                    ->label('Artikel')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Comment::STATUS_APPROVED => 'success',
                        Comment::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Comment::STATUS_APPROVED => 'Tayang',
                        Comment::STATUS_REJECTED => 'Ditolak',
                        default => 'Menunggu',
                    }),
                TextColumn::make('created_at')
                    ->label('Masuk')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Comment::STATUS_PENDING => 'Menunggu',
                        Comment::STATUS_APPROVED => 'Tayang',
                        Comment::STATUS_REJECTED => 'Ditolak',
                    ])
                    ->default(Comment::STATUS_PENDING),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Comment $record): bool => $record->status !== Comment::STATUS_APPROVED)
                    ->action(fn (Comment $record) => $record->update([
                        'status' => Comment::STATUS_APPROVED,
                        'moderated_by' => auth()->id(),
                        'moderated_at' => now(),
                    ])),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Comment $record): bool => $record->status !== Comment::STATUS_REJECTED)
                    ->action(fn (Comment $record) => $record->update([
                        'status' => Comment::STATUS_REJECTED,
                        'moderated_by' => auth()->id(),
                        'moderated_at' => now(),
                    ])),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComments::route('/'),
        ];
    }
}
