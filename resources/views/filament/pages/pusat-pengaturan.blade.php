<x-filament-panels::page>
    <p class="-mt-2 text-sm text-gray-500 dark:text-gray-400">
        Semua pengaturan pesantren dalam satu tempat. Klik kartu untuk membuka halaman pengaturannya.
    </p>

    @php($groups = $this->getGroups())

    @forelse ($groups as $group)
        <x-filament::section>
            <x-slot name="heading">{{ $group['title'] }}</x-slot>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($group['items'] as $item)
                    <a
                        href="{{ $item['url'] }}"
                        class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-400 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-500 dark:hover:bg-gray-800"
                    >
                        <span class="flex-shrink-0 rounded-lg bg-primary-50 p-2 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                            @svg($item['icon'], 'h-6 w-6')
                        </span>
                        <span class="min-w-0">
                            <span class="block font-medium text-gray-900 dark:text-gray-100">{{ $item['label'] }}</span>
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">{{ $item['description'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Tidak ada halaman pengaturan yang tersedia untuk akun Anda.
            </div>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
