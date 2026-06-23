<?php

namespace Modules\CMS\Filament\Resources\WebsiteSetting;

use App\Filament\Concerns\BuiltInWebsiteGate;
use App\Filament\Concerns\ModuleGate;
use Modules\CMS\Filament\Resources\WebsiteSetting\Pages\EditWebsiteSetting;
use Modules\CMS\Filament\Resources\WebsiteSetting\Pages\ListWebsiteSettings;
use App\Support\UiMode;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\CMS\Models\WebsiteSetting;

class WebsiteSettingResource extends Resource
{
    use BuiltInWebsiteGate;
    use ModuleGate;

    protected static ?string $model = WebsiteSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Pengaturan Website';

    protected static ?string $modelLabel = 'Pengaturan Website';

    protected static ?string $pluralModelLabel = 'Pengaturan Website';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan';

    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::builtInWebsiteEnabled()
            && static::moduleVisible('cms')
            && (auth()->user()?->can('manage_tenant_settings') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::builtInWebsiteEnabled()
            && static::moduleVisible('cms')
            && (auth()->user()?->can('manage_tenant_settings') ?? false);
    }

    public static function canCreate(): bool
    {
        return false; // setting dibuat via seed/Website Builder, bukan manual
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Kunci')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('value')
                    ->label('Nilai')
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                        : (string) $state
                    )
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('key')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebsiteSettings::route('/'),
            'edit' => EditWebsiteSetting::route('/{record}/edit'),
        ];
    }
}
