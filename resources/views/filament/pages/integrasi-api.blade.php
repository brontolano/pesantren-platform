<x-filament-panels::page>
    @if (filled($generatedKey))
        <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 dark:border-warning-600/50 dark:bg-warning-500/10">
            <div class="flex items-start gap-3">
                <x-filament::icon
                    icon="heroicon-o-key"
                    class="mt-0.5 h-6 w-6 shrink-0 text-warning-600 dark:text-warning-400"
                />

                <div class="flex-1 space-y-2">
                    <p class="font-semibold text-warning-800 dark:text-warning-200">
                        Kunci API berhasil dibuat — salin sekarang!
                    </p>

                    <p class="text-sm text-warning-700 dark:text-warning-300">
                        Demi keamanan, kunci ini hanya ditampilkan <strong>satu kali</strong>.
                        Simpan di tempat aman. Jika hilang, cabut kunci ini lalu buat yang baru.
                    </p>

                    <code class="block select-all break-all rounded-lg bg-white px-3 py-2 font-mono text-sm text-gray-900 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-100 dark:ring-white/10">{{ $generatedKey }}</code>

                    <x-filament::button
                        size="sm"
                        color="gray"
                        icon="heroicon-o-check"
                        wire:click="dismissGeneratedKey"
                    >
                        Saya sudah menyimpan
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
