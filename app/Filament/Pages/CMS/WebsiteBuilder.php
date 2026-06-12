<?php

namespace App\Filament\Pages\CMS;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Modules\CMS\Models\WebsiteSection;
use Modules\CMS\Models\WebsiteSetting;

class WebsiteBuilder extends Page
{
    use \App\Filament\Concerns\BuiltInWebsiteGate;

    protected string $view = 'filament.pages.cms.website-builder';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Website Builder';

    protected static ?string $title = 'Website Builder';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan & Komunikasi';

    protected static ?int $navigationSort = 3;

    public array $sections = [];

    public array $contact = [];

    public array $seo = [];

    public function mount(): void
    {
        $this->loadData();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::builtInWebsiteEnabled()
            && (auth()->user()?->can('manage_tenant_settings') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::builtInWebsiteEnabled()
            && (auth()->user()?->can('manage_tenant_settings') ?? false);
    }

    public function saveSections(): void
    {
        DB::transaction(function (): void {
            foreach (array_values($this->sections) as $index => $sectionData) {
                WebsiteSection::updateOrCreate(
                    ['tenant_id' => tenant()?->id, 'section_type' => $sectionData['section_type']],
                    [
                        'sort_order' => $index + 1,
                        'is_active' => (bool) ($sectionData['is_active'] ?? true),
                        'config' => $this->normalizeSectionConfigForPersistence(
                            $sectionData['section_type'],
                            $sectionData['config'] ?? []
                        ),
                    ]
                );
            }
        });

        Notification::make()
            ->title('Section website berhasil disimpan.')
            ->success()
            ->send();

        $this->loadData();
    }

    public function saveContact(): void
    {
        WebsiteSetting::updateOrCreate(
            ['tenant_id' => tenant()?->id, 'key' => 'contact'],
            ['value' => $this->contact]
        );

        Notification::make()
            ->title('Kontak pesantren berhasil disimpan.')
            ->success()
            ->send();
    }

    public function saveSeo(): void
    {
        WebsiteSetting::updateOrCreate(
            ['tenant_id' => tenant()?->id, 'key' => 'seo'],
            ['value' => $this->seo]
        );

        Notification::make()
            ->title('Pengaturan SEO berhasil disimpan.')
            ->success()
            ->send();
    }

    public function moveSectionUp(int $index): void
    {
        if ($index <= 0) {
            return;
        }

        [$this->sections[$index - 1], $this->sections[$index]] = [$this->sections[$index], $this->sections[$index - 1]];
    }

    public function moveSectionDown(int $index): void
    {
        if ($index >= count($this->sections) - 1) {
            return;
        }

        [$this->sections[$index], $this->sections[$index + 1]] = [$this->sections[$index + 1], $this->sections[$index]];
    }

    public function toggleSection(int $index): void
    {
        $this->sections[$index]['is_active'] = ! ($this->sections[$index]['is_active'] ?? true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview Website')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => url('/'), shouldOpenInNewTab: true),
        ];
    }

    protected function loadData(): void
    {
        $dbSections = WebsiteSection::query()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('section_type');

        $allTypes = ['hero', 'profile', 'blog', 'gallery', 'faq', 'testimonial', 'ppdb_cta'];

        $this->sections = collect($allTypes)->map(function (string $type) use ($dbSections): array {
            $section = $dbSections->get($type);
            $config = $this->prepareSectionConfigForEditor($type, $section?->config ?? []);

            return [
                'section_type' => $type,
                'label' => $this->sectionLabel($type),
                'sort_order' => $section?->sort_order ?? $this->defaultSortOrder($type),
                'is_active' => $section?->is_active ?? true,
                'config' => $config,
                'db_id' => $section?->id,
            ];
        })->sortBy('sort_order')->values()->toArray();

        $this->contact = WebsiteSetting::getTenantValue('contact', []);
        $this->seo = WebsiteSetting::getTenantValue('seo', []);
    }

    protected function prepareSectionConfigForEditor(string $type, array $config): array
    {
        $prepared = $config;

        if ($type === 'hero') {
            $prepared['stats_text'] = collect($config['stats'] ?? [])
                ->map(fn (array $item): string => trim(($item['label'] ?? '').'|'.($item['value'] ?? '')))
                ->filter()
                ->implode(PHP_EOL);
        }

        if ($type === 'profile') {
            $prepared['mission_text'] = collect($config['mission'] ?? [])->filter()->implode(PHP_EOL);
            $prepared['highlights_text'] = collect($config['highlights'] ?? [])->filter()->implode(PHP_EOL);
        }

        if ($type === 'faq') {
            $prepared['items_text'] = collect($config['items'] ?? [])
                ->map(fn (array $item): string => trim(($item['question'] ?? '').'|'.($item['answer'] ?? '')))
                ->filter()
                ->implode(PHP_EOL);
        }

        if ($type === 'testimonial') {
            $prepared['highlights_text'] = collect($config['highlights'] ?? [])->filter()->implode(PHP_EOL);
        }

        return $prepared;
    }

    protected function normalizeSectionConfigForPersistence(string $type, array $config): array
    {
        if ($type === 'hero') {
            $config['stats'] = $this->parseKeyValueLines($config['stats_text'] ?? '');
            unset($config['stats_text']);
        }

        if ($type === 'profile') {
            $config['mission'] = $this->parseLineList($config['mission_text'] ?? '');
            $config['highlights'] = $this->parseLineList($config['highlights_text'] ?? '');
            unset($config['mission_text'], $config['highlights_text']);
        }

        if ($type === 'faq') {
            $config['items'] = collect($this->parseKeyValueLines($config['items_text'] ?? ''))
                ->map(fn (array $item): array => [
                    'question' => $item['label'],
                    'answer' => $item['value'],
                ])
                ->all();
            unset($config['items_text']);
        }

        if ($type === 'testimonial') {
            $config['highlights'] = $this->parseLineList($config['highlights_text'] ?? '');
            unset($config['highlights_text']);
        }

        return $config;
    }

    protected function parseLineList(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn (?string $line): ?string => is_string($line) && trim($line) !== '' ? trim($line) : null)
            ->filter()
            ->values()
            ->all();
    }

    protected function parseKeyValueLines(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(function (?string $line): ?array {
                if (! is_string($line) || trim($line) === '') {
                    return null;
                }

                [$label, $content] = array_pad(explode('|', $line, 2), 2, '');
                $label = trim($label);
                $content = trim($content);

                if ($label === '' || $content === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'value' => $content,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function sectionLabel(string $type): string
    {
        return match ($type) {
            'hero' => 'Hero Banner',
            'profile' => 'Profil Pesantren',
            'blog' => 'Artikel / Blog',
            'gallery' => 'Galeri Foto',
            'faq' => 'FAQ',
            'testimonial' => 'Sambutan Pimpinan',
            'ppdb_cta' => 'CTA Pendaftaran (PPDB)',
            default => ucfirst($type),
        };
    }

    protected function defaultSortOrder(string $type): int
    {
        return match ($type) {
            'hero' => 1,
            'profile' => 2,
            'blog' => 3,
            'gallery' => 4,
            'faq' => 5,
            'testimonial' => 6,
            'ppdb_cta' => 7,
            default => 99,
        };
    }
}
