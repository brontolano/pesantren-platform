<x-filament-panels::page>
    <div class="mx-auto w-full max-w-lg">
        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
            Demi keamanan, buat password baru untuk akun Portal PPDB Anda. Password ini menggantikan
            password sementara dari panitia.
        </p>

        <form wire:submit="submit">
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit" icon="heroicon-o-check">
                    Simpan Password Baru
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
