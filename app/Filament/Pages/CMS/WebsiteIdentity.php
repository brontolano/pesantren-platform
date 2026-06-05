<?php

namespace App\Filament\Pages\CMS;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\CMS\Models\WebsiteSetting;

/**
 * Editor ramah untuk Identitas & Kontak website (key `branding` & `contact` di
 * WebsiteSetting). Menggantikan kebutuhan Website Builder bagi tenant berwebsite
 * custom — datanya dikonsumsi situs custom via /api/public/content/settings.
 */
class WebsiteIdentity extends Page implements HasForms
{
    use InteractsWithForms;
    use \App\Filament\Concerns\ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Identitas & Kontak';

    protected static ?string $title = 'Identitas & Kontak Website';

    protected static string|\UnitEnum|null $navigationGroup = 'Website & CMS';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.cms.website-identity';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return static::moduleVisible('cms') && (auth()->user()?->can('manage_tenant_settings') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $branding = WebsiteSetting::getTenantValue('branding', []);
        $contact = WebsiteSetting::getTenantValue('contact', []);
        $branding = is_array($branding) ? $branding : [];
        $contact = is_array($contact) ? $contact : [];

        $this->form->fill([
            'name' => $branding['name'] ?? null,
            'tagline' => $branding['tagline'] ?? null,
            'address' => $contact['address'] ?? null,
            'phone' => $contact['phone'] ?? null,
            'email' => $contact['email'] ?? null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Identitas')
                ->description('Nama & tagline pesantren yang tampil di website.')
                ->icon('heroicon-o-building-library')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Nama Pesantren')->maxLength(150),
                    TextInput::make('tagline')->label('Tagline')->maxLength(255),
                ]),
            Section::make('Kontak')
                ->icon('heroicon-o-phone')
                ->columns(2)
                ->schema([
                    Textarea::make('address')->label('Alamat')->rows(2)->maxLength(300)->columnSpanFull(),
                    TextInput::make('phone')->label('Telepon / WhatsApp')->maxLength(40),
                    TextInput::make('email')->label('Email')->email()->maxLength(120),
                ]),
        ]);
    }

    public function save(): void
    {
        $d = $this->form->getState();
        $clean = static fn (array $a): array => array_filter($a, static fn ($v) => $v !== null && $v !== '');

        WebsiteSetting::updateOrCreate(
            ['tenant_id' => tenant()?->id, 'key' => 'branding'],
            ['value' => $clean(['name' => $d['name'] ?? null, 'tagline' => $d['tagline'] ?? null])]
        );
        WebsiteSetting::updateOrCreate(
            ['tenant_id' => tenant()?->id, 'key' => 'contact'],
            ['value' => $clean(['address' => $d['address'] ?? null, 'phone' => $d['phone'] ?? null, 'email' => $d['email'] ?? null])]
        );

        Notification::make()->title('Identitas & kontak tersimpan')->success()->send();
    }
}
