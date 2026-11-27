<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-300">Informasi layanan, pemeliharaan, dan pembaruan resmi untuk pesantren Anda.</p>
        <div class="flex gap-2" role="group" aria-label="Folder informasi">
            <x-filament::button size="sm" :color="$folder === 'inbox' ? 'primary' : 'gray'" :aria-pressed="$folder === 'inbox'" wire:click="$set('folder', 'inbox')">Kotak masuk</x-filament::button>
            <x-filament::button size="sm" :color="$folder === 'archive' ? 'primary' : 'gray'" :aria-pressed="$folder === 'archive'" wire:click="$set('folder', 'archive')">Arsip</x-filament::button>
        </div>

        @forelse ($announcements as $announcement)
            @php($read = $announcement->reads->isNotEmpty())
            <article class="rounded-xl border p-4 sm:p-5 {{ $announcement->priority === 'mendesak' ? 'border-rose-300 bg-rose-50/70 dark:border-rose-900 dark:bg-rose-950/20' : 'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs font-medium">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ ucfirst($announcement->category) }}</span>
                            @if ($announcement->priority !== 'normal')
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-900 dark:bg-amber-950 dark:text-amber-200">{{ ucfirst($announcement->priority) }}</span>
                            @endif
                            <time datetime="{{ $announcement->published_at?->toIso8601String() }}" class="text-gray-500">{{ $announcement->published_at?->translatedFormat('d M Y, H:i') }}</time>
                        </div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $announcement->title }}</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($read)
                            <span class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Sudah dibaca</span>
                        @else
                            <x-filament::button size="sm" color="gray" wire:click="markAsRead({{ $announcement->id }})" wire:loading.attr="disabled" wire:target="markAsRead">Tandai sudah dibaca</x-filament::button>
                        @endif
                        <x-filament::button size="sm" color="gray" wire:click="toggleArchive({{ $announcement->id }})" wire:loading.attr="disabled" wire:target="toggleArchive">{{ $folder === 'archive' ? 'Kembalikan' : 'Arsipkan' }}</x-filament::button>
                    </div>
                </div>
                <div class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $announcement->body }}</div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 px-5 py-12 text-center dark:border-gray-700">
                <h2 class="font-semibold text-gray-900 dark:text-white">Belum ada informasi platform</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Pesan resmi dan informasi teknis akan tampil di sini.</p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
