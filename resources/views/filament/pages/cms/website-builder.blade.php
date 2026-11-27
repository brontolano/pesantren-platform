<x-filament-panels::page>

    {{-- ============================================================
         SECTION MANAGER
         ============================================================ --}}
    <x-filament::section>
        <x-slot name="heading">Kelola Section Website</x-slot>
        <x-slot name="description">Aktifkan/nonaktifkan bagian situs dan atur urutannya. Simpan sebagai draf, lihat pratinjau, lalu terbitkan jika hasilnya sudah sesuai.</x-slot>

        @if ($hasDraft)
            <div role="status" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                @if ($hasUnsavedChanges)
                    Ada perubahan yang belum disimpan ke draf. Simpan sebelum membuat pratinjau atau menerbitkannya.
                @else
                    Ada draf website yang belum diterbitkan. Pengunjung masih melihat versi yang sudah terbit.
                @endif
            </div>
        @else
            <div role="note" class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                Perubahan disimpan sebagai draf terlebih dahulu. Website publik tidak berubah sampai Anda memilih Terbitkan Perubahan.
            </div>
        @endif

        <div class="space-y-3">
            @foreach ($sections as $i => $section)
                <div @class([
                    'flex items-center gap-3 rounded-xl border p-4 transition',
                    'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700' => $section['is_active'],
                    'bg-gray-50 dark:bg-gray-900 border-dashed border-gray-300 dark:border-gray-600 opacity-60' => ! $section['is_active'],
                ])>
                    {{-- Toggle --}}
                    <div class="flex-shrink-0">
                        <button wire:click="toggleSection({{ $i }})" type="button"
                            role="switch" aria-checked="{{ $section['is_active'] ? 'true' : 'false' }}"
                            aria-label="{{ ($section['is_active'] ? 'Nonaktifkan bagian ' : 'Aktifkan bagian ') . $section['label'] }}"
                            @class([
                                'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2',
                                'bg-primary-600' => $section['is_active'],
                                'bg-gray-300 dark:bg-gray-600' => ! $section['is_active'],
                            ])>
                            <span @class([
                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                'translate-x-6' => $section['is_active'],
                                'translate-x-1' => ! $section['is_active'],
                            ])></span>
                        </button>
                    </div>

                    {{-- Label + type --}}
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-sm text-gray-900 dark:text-gray-100">{{ $section['label'] }}</p>
                    </div>

                    {{-- Status badge --}}
                    <span @class([
                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-300' => $section['is_active'],
                        'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => ! $section['is_active'],
                    ])>
                        {{ $section['is_active'] ? 'Aktif' : 'Nonaktif' }}
                    </span>

                    {{-- Reorder buttons --}}
                    <div class="flex flex-col gap-0.5">
                        <button wire:click="moveSectionUp({{ $i }})" type="button"
                            aria-label="Naikkan bagian {{ $section['label'] }}"
                            @disabled($i === 0)
                            class="min-h-6 min-w-6 rounded p-1 text-gray-400 hover:text-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600 dark:hover:text-gray-200 disabled:opacity-30 disabled:cursor-not-allowed">
                            <x-heroicon-s-chevron-up class="w-4 h-4"/>
                        </button>
                        <button wire:click="moveSectionDown({{ $i }})" type="button"
                            aria-label="Turunkan bagian {{ $section['label'] }}"
                            @disabled($i === count($sections) - 1)
                            class="min-h-6 min-w-6 rounded p-1 text-gray-400 hover:text-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600 dark:hover:text-gray-200 disabled:opacity-30 disabled:cursor-not-allowed">
                            <x-heroicon-s-chevron-down class="w-4 h-4"/>
                        </button>
                    </div>
                </div>

                <div class="mt-3 grid gap-4 rounded-xl bg-gray-50 p-4 dark:bg-gray-900/40">
                    @if ($section['section_type'] === 'hero')
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Teks Kecil di Atas Judul</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.eyebrow"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Judul</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.title"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div class="md:col-span-2">
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Deskripsi</label>
                                <textarea wire:model="sections.{{ $i }}.config.description" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Teks Tombol Utama</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.primaryCtaLabel"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Tautan Tombol Utama</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.primaryCtaHref"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div class="md:col-span-2">
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Statistik (Angka Sorotan)</label>
                                <textarea wire:model="sections.{{ $i }}.config.stats_text" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-mono dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    placeholder="Program|Muadalah&#10;Asrama|Putra & Putri"></textarea>
                                <p class="mt-1 text-xs text-gray-500">Format: satu baris per item, `Label|Value`.</p>
                            </div>
                        </div>
                    @elseif ($section['section_type'] === 'profile')
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Judul</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.title"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Tentang</label>
                                <textarea wire:model="sections.{{ $i }}.config.about" rows="4"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Visi</label>
                                <textarea wire:model="sections.{{ $i }}.config.vision" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Misi</label>
                                <textarea wire:model="sections.{{ $i }}.config.mission_text" rows="4"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    placeholder="Satu misi per baris"></textarea>
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Sorotan</label>
                                <textarea wire:model="sections.{{ $i }}.config.highlights_text" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    placeholder="Satu highlight per baris"></textarea>
                            </div>
                        </div>
                    @elseif ($section['section_type'] === 'blog')
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Judul</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.title"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Limit Artikel</label>
                                <input type="number" min="1" wire:model="sections.{{ $i }}.config.limit"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div class="md:col-span-2">
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Deskripsi</label>
                                <textarea wire:model="sections.{{ $i }}.config.description" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
                            </div>
                        </div>
                    @elseif ($section['section_type'] === 'gallery')
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Judul</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.title"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Limit Galeri</label>
                                <input type="number" min="1" wire:model="sections.{{ $i }}.config.limit"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div class="md:col-span-2">
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Deskripsi</label>
                                <textarea wire:model="sections.{{ $i }}.config.description" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
                            </div>
                        </div>
                    @elseif ($section['section_type'] === 'faq')
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Judul</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.title"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Deskripsi</label>
                                <textarea wire:model="sections.{{ $i }}.config.description" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Daftar FAQ</label>
                                <textarea wire:model="sections.{{ $i }}.config.items_text" rows="4"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-mono dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    placeholder="Kapan pendaftaran dibuka?|Setiap hari selama kuota tersedia."></textarea>
                                <p class="mt-1 text-xs text-gray-500">Format: satu baris per item, `Pertanyaan|Jawaban`.</p>
                            </div>
                        </div>
                    @elseif ($section['section_type'] === 'testimonial')
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Judul</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.title"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Nama Pimpinan</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.leaderName"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div class="md:col-span-2">
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Deskripsi</label>
                                <textarea wire:model="sections.{{ $i }}.config.description" rows="2"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Quote</label>
                                <textarea wire:model="sections.{{ $i }}.config.quote" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Sorotan</label>
                                <textarea wire:model="sections.{{ $i }}.config.highlights_text" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    placeholder="Satu highlight per baris"></textarea>
                            </div>
                        </div>
                    @elseif ($section['section_type'] === 'ppdb_cta')
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Judul</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.title"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Label Tombol</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.buttonLabel"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div class="md:col-span-2">
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Isi Teks</label>
                                <textarea wire:model="sections.{{ $i }}.config.body" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Link Tombol</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.buttonHref"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Teks Bantuan</label>
                                <input type="text" wire:model="sections.{{ $i }}.config.helperText"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex justify-end">
            <x-filament::button wire:click="saveSections" icon="heroicon-o-check" wire:loading.attr="disabled" wire:target="saveSections">
                Simpan Draf Website
            </x-filament::button>
        </div>
    </x-filament::section>

    {{-- ============================================================
         KONTAK PESANTREN
         ============================================================ --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Kontak Pesantren</x-slot>
        <x-slot name="description">Informasi kontak publik. Perubahan disimpan sebagai draf dan terlihat pengunjung setelah diterbitkan.</x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="fi-fo-field-wrp-label block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Alamat</label>
                <textarea wire:model="contact.address" rows="3"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    placeholder="Jl. Contoh No. 1, Kota, Provinsi"></textarea>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="fi-fo-field-wrp-label block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Telepon / WA</label>
                    <input type="tel" wire:model="contact.phone"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        placeholder="08xx-xxxx-xxxx">
                </div>
                <div>
                    <label class="fi-fo-field-wrp-label block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Email</label>
                    <input type="email" wire:model="contact.email"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        placeholder="info@pesantren.sch.id">
                    @error('contact.email')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="fi-fo-field-wrp-label block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Link Google Maps</label>
                    <input type="url" wire:model="contact.maps_url"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        placeholder="https://maps.google.com/...">
                    @error('contact.maps_url')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <x-filament::button wire:click="saveContact" icon="heroicon-o-check" wire:loading.attr="disabled" wire:target="saveContact">
                Simpan Kontak ke Draf
            </x-filament::button>
        </div>
    </x-filament::section>

    {{-- ============================================================
         SEO
         ============================================================ --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">SEO & Meta Tags</x-slot>
        <x-slot name="description">Metadata halaman utama website. Perubahan disimpan sebagai draf dan berlaku setelah diterbitkan.</x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="fi-fo-field-wrp-label block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Meta Title <span class="text-gray-400 font-normal">(maks 70 karakter)</span></label>
                <input type="text" wire:model="seo.meta_title" maxlength="70"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    placeholder="Biarkan kosong untuk pakai nama pesantren">
                @error('seo.meta_title')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="fi-fo-field-wrp-label block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Robots</label>
                <input type="text" wire:model="seo.robots"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    placeholder="index,follow">
                @error('seo.robots')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="fi-fo-field-wrp-label block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Meta Description <span class="text-gray-400 font-normal">(maks 160 karakter)</span></label>
                <textarea wire:model="seo.meta_description" rows="2" maxlength="160"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    placeholder="Deskripsi singkat pesantren untuk mesin pencari."></textarea>
                @error('seo.meta_description')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="fi-fo-field-wrp-label block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">OG Image URL <span class="text-gray-400 font-normal">(untuk share sosmed)</span></label>
                <input type="url" wire:model="seo.image_url"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    placeholder="https://...">
                @error('seo.image_url')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <x-filament::button wire:click="saveSeo" icon="heroicon-o-check" wire:loading.attr="disabled" wire:target="saveSeo">
                Simpan SEO ke Draf
            </x-filament::button>
        </div>
    </x-filament::section>

</x-filament-panels::page>
