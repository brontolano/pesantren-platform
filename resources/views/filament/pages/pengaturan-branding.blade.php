<x-filament-panels::page>

    {{-- ====================================================================
         Preview logo tersimpan — ditampilkan via route /_tenant-storage/{path}
         yang meng-serve file dari disk 'public' tenant secara langsung (PHP).
         Tidak menggunakan Storage::url() karena symlink /storage → app/public
         tidak mencakup path tenant (storage/tenantXXX/app/public/).
         ==================================================================== --}}
    @if ($this->currentLogoPath)
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm
                dark:border-gray-700 dark:bg-gray-800">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            Logo Saat Ini
        </p>
        <div class="flex flex-wrap items-center gap-4">
            <img
                src="{{ url('/_tenant-storage/' . ltrim($this->currentLogoPath, '/')) }}"
                alt="Logo pesantren"
                class="h-14 max-w-[180px] rounded-lg object-contain ring-1 ring-gray-200 dark:ring-gray-700"
                onerror="this.replaceWith(document.createTextNode('⚠ Gambar tidak dapat dimuat'))"
            />
            <div class="text-sm">
                <p class="font-medium text-gray-700 dark:text-gray-200">
                    {{ basename($this->currentLogoPath) }}
                </p>
                <p class="mt-0.5 text-gray-400 dark:text-gray-500">
                    Upload file baru di bawah untuk mengganti logo ini.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Preview favicon tersimpan — pola sama dengan logo (route /_tenant-storage). --}}
    @if ($this->currentFaviconPath)
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm
                dark:border-gray-700 dark:bg-gray-800">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            Favicon Saat Ini
        </p>
        <div class="flex flex-wrap items-center gap-4">
            <img
                src="{{ url('/_tenant-storage/' . ltrim($this->currentFaviconPath, '/')) }}"
                alt="Favicon pesantren"
                class="h-10 w-10 rounded-lg object-contain ring-1 ring-gray-200 dark:ring-gray-700"
                onerror="this.replaceWith(document.createTextNode('⚠ Gambar tidak dapat dimuat'))"
            />
            <div class="text-sm">
                <p class="font-medium text-gray-700 dark:text-gray-200">
                    {{ basename($this->currentFaviconPath) }}
                </p>
                <p class="mt-0.5 text-gray-400 dark:text-gray-500">
                    Upload file baru di bawah untuk mengganti favicon ini.
                </p>
            </div>
        </div>
    </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Simpan Pengaturan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
