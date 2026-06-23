<?php

namespace Modules\CMS\Filament\Resources\WebsiteSetting\Pages;

use Modules\CMS\Filament\Resources\WebsiteSetting\WebsiteSettingResource;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditWebsiteSetting extends EditRecord
{
    protected static string $resource = WebsiteSettingResource::class;

    public function form(Schema $schema): Schema
    {
        $key = $this->record?->key ?? '';

        return $schema->components(
            match (true) {
                $key === 'contact' => [
                    Section::make('Kontak Pesantren')->schema([
                        TextInput::make('value.address')
                            ->label('Alamat')
                            ->maxLength(500),
                        TextInput::make('value.phone')
                            ->label('Telepon / WA')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('value.email')
                            ->label('Email')
                            ->email()
                            ->maxLength(100),
                        TextInput::make('value.maps_url')
                            ->label('Link Google Maps')
                            ->url()
                            ->maxLength(500),
                    ])->columns(2),
                ],
                $key === 'seo' => [
                    Section::make('SEO Website')->schema([
                        TextInput::make('value.meta_title')
                            ->label('Meta Title')
                            ->maxLength(70),
                        Textarea::make('value.meta_description')
                            ->label('Meta Description')
                            ->rows(2)
                            ->maxLength(160),
                        TextInput::make('value.image_url')
                            ->label('OG Image URL')
                            ->url()
                            ->maxLength(500),
                        TextInput::make('value.robots')
                            ->label('Robots')
                            ->default('index,follow')
                            ->maxLength(50),
                    ])->columns(2),
                ],
                // Kunci kanonik modul ini = 'profile' (selaras WebsiteSection
                // section_type 'profile', ProfileResource, WebsiteBuilder,
                // TenantWebsiteService). Kunci lama 'profil' tetap diterima
                // (kompat-mundur) agar data lama tak jatuh ke editor JSON mentah.
                in_array($key, ['profile', 'profil'], true) => [
                    Section::make('Profil Pesantren')->schema([
                        TextInput::make('value.title')
                            ->label('Judul Section')
                            ->maxLength(100),
                        Textarea::make('value.about')
                            ->label('Tentang Pesantren')
                            ->rows(4)
                            ->maxLength(1000),
                        TextInput::make('value.vision')
                            ->label('Visi')
                            ->maxLength(500),
                        Textarea::make('value.mission')
                            ->label('Misi (pisahkan dengan baris baru)')
                            ->rows(4)
                            ->helperText('Satu misi per baris.')
                            ->maxLength(2000),
                    ])->columns(2),
                ],
                default => [
                    Section::make('Edit Nilai')->schema([
                        KeyValue::make('value')
                            ->label('Nilai (JSON key-value)')
                            ->keyLabel('Kunci')
                            ->valueLabel('Nilai')
                            ->addActionLabel('Tambah'),
                    ]),
                ],
            }
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

