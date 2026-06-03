<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\CreateSetting;
use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Filament\Resources\Settings\Pages\ListSettings;
use App\Models\Setting;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings & Branding';

    protected static ?string $modelLabel = 'Setting';

    protected static ?string $pluralModelLabel = 'Settings';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::userCanManageSettings();
    }

    public static function canViewAny(): bool
    {
        return static::userCanManageSettings();
    }

    public static function canCreate(): bool
    {
        return static::userCanManageSettings();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::userCanManageSettings() && $record->tenant_id === tenant('id');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('key')
                ->label('Jenis Setting')
                ->options(static::settingOptions())
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('value.text')
                ->label('Nilai Teks')
                ->required(fn (Get $get): bool => in_array($get('key'), ['branding_name', 'branding_tagline'], true))
                ->visible(fn (Get $get): bool => in_array($get('key'), ['branding_name', 'branding_tagline'], true))
                ->maxLength(255),
            ColorPicker::make('value.color')
                ->label('Warna')
                ->required(fn (Get $get): bool => in_array($get('key'), ['branding_primary_color', 'branding_secondary_color'], true))
                ->visible(fn (Get $get): bool => in_array($get('key'), ['branding_primary_color', 'branding_secondary_color'], true)),
            FileUpload::make('value.logo_path')
                ->label('Logo')
                ->disk('public')
                ->directory('branding')
                ->image()
                ->imageEditor()
                ->visible(fn (Get $get): bool => $get('key') === 'branding_logo')
                ->required(fn (Get $get): bool => $get('key') === 'branding_logo'),
            KeyValue::make('value.fields')
                ->label('Konfigurasi Field PPDB')
                ->helperText('Isi mode field dengan `required`, `optional`, atau `hidden`.')
                ->required(fn (Get $get): bool => $get('key') === 'ppdb_form_fields')
                ->visible(fn (Get $get): bool => $get('key') === 'ppdb_form_fields')
                ->keyLabel('Field')
                ->valueLabel('Mode'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Key')
                    ->formatStateUsing(fn (string $state): string => static::settingOptions()[$state] ?? $state)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('value')
                    ->label('Nilai')
                    ->formatStateUsing(function (mixed $state): string {
                        if (! is_array($state)) {
                            return '-';
                        }

                        if (isset($state['fields']) && is_array($state['fields'])) {
                            return collect($state['fields'])
                                ->map(fn (mixed $mode, string $field): string => "{$field}: {$mode}")
                                ->implode(', ');
                        }

                        return $state['text'] ?? $state['color'] ?? $state['logo_path'] ?? '-';
                    })
                    ->wrap(),
                ImageColumn::make('value.logo_path')
                    ->label('Preview Logo')
                    ->disk('public')
                    ->defaultImageUrl(null),
                TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('key');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSettings::route('/'),
            'create' => CreateSetting::route('/create'),
            'edit' => EditSetting::route('/{record}/edit'),
        ];
    }

    protected static function userCanManageSettings(): bool
    {
        return Auth::user()?->can('manage_tenant_settings') ?? false;
    }

    protected static function settingOptions(): array
    {
        return [
            'branding_name' => 'Nama Branding',
            'branding_tagline' => 'Tagline Branding',
            'branding_primary_color' => 'Warna Primer',
            'branding_secondary_color' => 'Warna Sekunder',
            'branding_logo' => 'Logo Tenant',
            'ppdb_form_fields' => 'Field Form PPDB',
        ];
    }
}
