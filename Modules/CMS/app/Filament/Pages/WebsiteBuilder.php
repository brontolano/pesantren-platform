<?php

namespace Modules\CMS\Filament\Pages;

use App\Filament\Concerns\BuiltInWebsiteGate;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Modules\CMS\Models\WebsiteSection;
use Modules\CMS\Models\WebsiteSetting;

class WebsiteBuilder extends Page
{
    use BuiltInWebsiteGate;

    private const SECTION_TYPES = ['hero', 'profile', 'blog', 'gallery', 'faq', 'testimonial', 'ppdb_cta'];

    protected string $view = 'filament.pages.cms.website-builder';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Tampilan Website (Lanjutan)';

    protected static ?string $title = 'Atur Tampilan Website';

    protected ?string $subheading = 'Simpan sebagai draf, periksa pratinjau, lalu terbitkan jika sudah siap. Pengunjung tetap melihat versi terbit sampai Anda menerbitkan draf.';

    protected static string|\UnitEnum|null $navigationGroup = 'Website Pesantren';

    protected static ?int $navigationSort = 3;

    public array $sections = [];

    public array $contact = [];

    public array $seo = [];

    public bool $hasDraft = false;

    public bool $hasUnsavedChanges = false;

    public function mount(): void
    {
        $this->loadData();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::builtInWebsiteEnabled()
            && (auth()->user()?->can('manage_tenant_settings') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::builtInWebsiteEnabled()
            && (auth()->user()?->can('manage_tenant_settings') ?? false);
    }

    public function saveSections(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDraftFields();
        $this->persistDraft();

        Notification::make()
            ->title('Draf website tersimpan')
            ->body('Perubahan belum terlihat oleh pengunjung sampai diterbitkan.')
            ->success()
            ->send();

        $this->loadData();
    }

    public function saveContact(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDraftFields();
        $this->persistDraft();

        Notification::make()
            ->title('Kontak disimpan sebagai draf')
            ->body('Informasi publik berubah setelah draf diterbitkan.')
            ->success()
            ->send();
    }

    public function saveSeo(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDraftFields();
        $this->persistDraft();

        Notification::make()
            ->title('Pengaturan SEO disimpan sebagai draf')
            ->body('Perubahan metadata publik akan diterapkan saat draf diterbitkan.')
            ->success()
            ->send();
    }

    public function moveSectionUp(int $index): void
    {
        abort_unless(static::canAccess(), 403);
        if ($index <= 0 || $index >= count($this->sections)) {
            return;
        }

        [$this->sections[$index - 1], $this->sections[$index]] = [$this->sections[$index], $this->sections[$index - 1]];
        $this->refreshDraftDirtyState();
    }

    public function moveSectionDown(int $index): void
    {
        abort_unless(static::canAccess(), 403);
        if ($index < 0 || $index >= count($this->sections) - 1) {
            return;
        }

        [$this->sections[$index], $this->sections[$index + 1]] = [$this->sections[$index + 1], $this->sections[$index]];
        $this->refreshDraftDirtyState();
    }

    public function toggleSection(int $index): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(array_key_exists($index, $this->sections), 404);
        $this->sections[$index]['is_active'] = ! ($this->sections[$index]['is_active'] ?? true);
        $this->refreshDraftDirtyState();
    }

    public function updated(mixed $property = null, mixed $value = null): void
    {
        $this->refreshDraftDirtyState();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(fn (): string => $this->hasDraft ? 'Pratinjau Draf' : 'Lihat Website')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => $this->hasDraft ? $this->previewDraftUrl() : $this->publicWebsiteUrl(), shouldOpenInNewTab: true)
                ->tooltip(fn (): string => $this->hasDraft
                    ? 'Tautan pratinjau ini berlaku 10 menit dan hanya menampilkan draf pesantren ini.'
                    : 'Buka website publik pesantren.'),
            Action::make('publish')
                ->label('Terbitkan Perubahan')
                ->icon('heroicon-o-globe-alt')
                ->color('success')
                ->visible(fn (): bool => $this->hasDraft)
                ->disabled(fn (): bool => $this->hasUnsavedChanges)
                ->tooltip(fn (): ?string => $this->hasUnsavedChanges ? 'Simpan perubahan ke draf sebelum menerbitkan.' : null)
                ->requiresConfirmation()
                ->modalHeading('Terbitkan perubahan website?')
                ->modalDescription('Konten, kontak, dan metadata dari draf akan menggantikan informasi yang sekarang terlihat di website publik.')
                ->modalSubmitActionLabel('Terbitkan website')
                ->action(fn () => $this->publishWebsite()),
            Action::make('discard')
                ->label('Buang Draf')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => $this->hasDraft)
                ->requiresConfirmation()
                ->modalHeading('Buang draf website?')
                ->modalDescription('Draf yang belum diterbitkan akan dihapus. Website publik tidak berubah.')
                ->modalSubmitActionLabel('Buang draf')
                ->action(fn () => $this->discardDraft()),
        ];
    }

    public function publishWebsite(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDraftFields();
        $storedDraft = WebsiteSetting::getTenantValue('website_builder_draft');
        abort_unless(is_array($storedDraft) && is_array($storedDraft['sections'] ?? null), 404);
        $draft = array_intersect_key($storedDraft, array_flip(['sections', 'contact', 'seo']));
        abort_unless($this->draftPayload() == $draft, 409, 'Simpan perubahan ke draf sebelum menerbitkan.');

        DB::transaction(function () use ($draft): void {
            foreach ($draft['sections'] as $section) {
                WebsiteSection::updateOrCreate(
                    ['tenant_id' => tenant()?->id, 'section_type' => $section['section_type']],
                    [
                        'sort_order' => $section['sort_order'],
                        'is_active' => $section['is_active'],
                        'config' => $section['config'],
                    ]
                );
            }

            WebsiteSetting::updateOrCreate(
                ['tenant_id' => tenant()?->id, 'key' => 'contact'],
                ['value' => $draft['contact']]
            );
            WebsiteSetting::updateOrCreate(
                ['tenant_id' => tenant()?->id, 'key' => 'seo'],
                ['value' => $draft['seo']]
            );
            WebsiteSetting::query()->where('key', 'website_builder_draft')->delete();
        });

        $this->loadData();
        Notification::make()->title('Perubahan website telah diterbitkan')->success()->send();
    }

    public function discardDraft(): void
    {
        abort_unless(static::canAccess(), 403);
        WebsiteSetting::query()->where('key', 'website_builder_draft')->delete();
        $this->loadData();
        Notification::make()->title('Draf website dibuang')->body('Website publik tidak berubah.')->success()->send();
    }

    protected function loadData(): void
    {
        $dbSections = WebsiteSection::query()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('section_type');

        $allTypes = self::SECTION_TYPES;

        $draft = WebsiteSetting::getTenantValue('website_builder_draft');
        $draftSections = is_array($draft) && is_array($draft['sections'] ?? null)
            ? collect($draft['sections'])->keyBy('section_type')
            : collect();
        $this->hasDraft = is_array($draft);
        $this->hasUnsavedChanges = false;

        $this->sections = collect($allTypes)->map(function (string $type) use ($dbSections, $draftSections): array {
            $section = $dbSections->get($type);
            $draftSection = $draftSections->get($type);
            $config = $this->prepareSectionConfigForEditor($type, $draftSection['config'] ?? $section?->config ?? []);

            return [
                'section_type' => $type,
                'label' => $this->sectionLabel($type),
                'sort_order' => $draftSection['sort_order'] ?? $section?->sort_order ?? $this->defaultSortOrder($type),
                'is_active' => $draftSection['is_active'] ?? $section?->is_active ?? true,
                'config' => $config,
                'db_id' => $section?->id,
            ];
        })->sortBy('sort_order')->values()->toArray();

        $this->contact = $draft['contact'] ?? WebsiteSetting::getTenantValue('contact', []);
        $this->seo = $draft['seo'] ?? WebsiteSetting::getTenantValue('seo', []);
    }

    protected function draftPayload(): array
    {
        $sections = array_values($this->sections);
        abort_unless(collect($sections)->every(fn ($section): bool => is_array($section)), 422);
        $actualTypes = collect($sections)->pluck('section_type')->sort()->values()->all();
        $expectedTypes = collect(self::SECTION_TYPES)->sort()->values()->all();
        abort_unless($actualTypes === $expectedTypes, 422, 'Daftar bagian website tidak valid. Muat ulang halaman sebelum menyimpan.');
        abort_unless(collect($sections)->every(fn (array $section): bool => is_array($section['config'] ?? null)), 422);

        return [
            'sections' => collect($sections)->map(function (array $section, int $index): array {
                return [
                    'section_type' => $section['section_type'],
                    'sort_order' => $index + 1,
                    'is_active' => (bool) ($section['is_active'] ?? true),
                    'config' => $this->normalizeSectionConfigForPersistence($section['section_type'], $section['config'] ?? []),
                ];
            })->values()->all(),
            'contact' => $this->contact,
            'seo' => $this->seo,
        ];
    }

    protected function persistDraft(): void
    {
        WebsiteSetting::updateOrCreate(
            ['tenant_id' => tenant()?->id, 'key' => 'website_builder_draft'],
            ['value' => [
                ...$this->draftPayload(),
                'saved_at' => now()->toIso8601String(),
                'saved_by' => auth()->id(),
            ]]
        );

        $this->loadData();
    }

    protected function validateDraftFields(): void
    {
        $this->validate([
            'contact.address' => ['nullable', 'string', 'max:500'],
            'contact.phone' => ['nullable', 'string', 'max:40'],
            'contact.email' => ['nullable', 'email', 'max:120'],
            'contact.maps_url' => ['nullable', 'url', 'max:2048'],
            'seo.meta_title' => ['nullable', 'string', 'max:70'],
            'seo.meta_description' => ['nullable', 'string', 'max:160'],
            'seo.robots' => ['nullable', 'string', 'max:80'],
            'seo.image_url' => ['nullable', 'url', 'max:2048'],
        ]);
    }

    protected function refreshDraftDirtyState(): void
    {
        if (! $this->hasDraft) {
            $this->hasUnsavedChanges = false;

            return;
        }

        $storedDraft = WebsiteSetting::getTenantValue('website_builder_draft');
        $storedPayload = is_array($storedDraft)
            ? array_intersect_key($storedDraft, array_flip(['sections', 'contact', 'seo']))
            : [];

        $this->hasUnsavedChanges = $this->draftPayload() != $storedPayload;
    }

    protected function previewDraftUrl(): string
    {
        $path = URL::temporarySignedRoute(
            'tenant.website.preview',
            now()->addMinutes(10),
            ['tenantKey' => tenant()?->getTenantKey()],
            absolute: false
        );

        return $this->publicWebsiteUrl().$path;
    }

    protected function publicWebsiteUrl(): string
    {
        $domain = tenant()?->domains()->where('type', 'website')->value('domain')
            ?? tenant()?->domains()->where('type', 'subdomain')->value('domain')
            ?? request()->getHost();

        return request()->getScheme().'://'.$domain.'/';
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
