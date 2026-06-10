<x-filament-panels::page>
    <form wire:submit="simpan" class="space-y-4">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                Simpan &amp; proses dokumen
            </x-filament::button>
        </div>
    </form>

    @php($daftar = $this->daftarSumber())

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 p-4 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Daftar Sumber Pengetahuan</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Dokumen berstatus <strong>siap</strong> akan dirujuk Khadim Digital saat menjawab.
            </p>
        </div>

        @if ($daftar->isEmpty())
            <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Belum ada sumber pengetahuan. Silakan unggah dokumen pertama Anda di atas.
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-gray-600 dark:border-gray-700 dark:text-gray-300">
                        <th class="p-3">Judul</th>
                        <th class="p-3">Kategori</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Jumlah potongan</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daftar as $sumber)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50" wire:key="sumber-{{ $sumber->id }}">
                            <td class="p-3 font-medium text-gray-800 dark:text-gray-100">
                                {{ $sumber->judul }}
                                @if ($sumber->status === 'gagal' && $sumber->error_ringkas)
                                    <p class="mt-1 text-xs font-normal text-danger-600 dark:text-danger-400">{{ $sumber->error_ringkas }}</p>
                                @endif
                            </td>
                            <td class="p-3 text-gray-600 dark:text-gray-300">{{ ucfirst($sumber->kategori) }}</td>
                            <td class="p-3">
                                <x-filament::badge :color="match ($sumber->status) {
                                    'siap' => 'success',
                                    'gagal' => 'danger',
                                    'memproses' => 'warning',
                                    default => 'gray',
                                }">
                                    {{ $sumber->status }}
                                </x-filament::badge>
                            </td>
                            <td class="p-3 text-right text-gray-600 dark:text-gray-300">{{ $sumber->jumlah_chunk }}</td>
                            <td class="p-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path"
                                        wire:click="prosesUlang({{ $sumber->id }})"
                                        wire:confirm="Proses ulang dokumen ini? Seluruh potongan lama akan diganti.">
                                        Re-ingest
                                    </x-filament::button>
                                    <x-filament::button size="sm" color="danger" icon="heroicon-o-trash"
                                        wire:click="hapusTuntas({{ $sumber->id }})"
                                        wire:confirm="Hapus tuntas sumber ini? Sumber, seluruh potongan, vektor, dan berkas fisiknya dihapus PERMANEN dan tidak dapat dikembalikan.">
                                        Hapus tuntas
                                    </x-filament::button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>
