<x-filament-panels::page>
    {{-- Pratinjau impor menunggu konfirmasi --}}
    @if ($this->importPratinjau !== null)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 space-y-3 dark:bg-amber-950/30 dark:border-amber-700">
            <h3 class="font-semibold text-amber-800 dark:text-amber-200">Pratinjau Impor Kebijakan</h3>

            @if (count($this->importPratinjau['diff']) === 0)
                <p class="text-sm text-amber-700 dark:text-amber-300">Tidak ada perubahan — semua nilai pada berkas sama dengan kebijakan saat ini.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-amber-800 dark:text-amber-200">
                            <th class="py-1 pr-2">Kebijakan</th>
                            <th class="py-1 pr-2">Nilai sekarang</th>
                            <th class="py-1">Nilai baru</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->importPratinjau['diff'] as $row)
                            <tr class="border-t border-amber-200 dark:border-amber-800 align-top">
                                <td class="py-1 pr-2 font-medium">{{ $row['label'] }}<div class="text-xs text-gray-500">{{ $row['key'] }}</div></td>
                                <td class="py-1 pr-2 font-mono text-xs">{{ json_encode($row['dari'], JSON_UNESCAPED_UNICODE) }}</td>
                                <td class="py-1 font-mono text-xs">{{ json_encode($row['ke'], JSON_UNESCAPED_UNICODE) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if (count($this->importPratinjau['ditolak'] ?? []) > 0)
                <div class="text-xs text-red-700 dark:text-red-300 space-y-1">
                    <p class="font-semibold">Ditolak (nilai tidak valid — dilewati saat diterapkan):</p>
                    @foreach ($this->importPratinjau['ditolak'] as $tolak)
                        <p>{{ $tolak['label'] }} ({{ $tolak['key'] }}): {{ $tolak['alasan'] }}</p>
                    @endforeach
                </div>
            @endif

            @if (count($this->importPratinjau['diabaikan']) > 0)
                <p class="text-xs text-amber-700 dark:text-amber-300">
                    Diabaikan (tidak dikenal): {{ implode(', ', $this->importPratinjau['diabaikan']) }}
                </p>
            @endif

            <div class="flex gap-2">
                @if (count($this->importPratinjau['diff']) > 0)
                    <x-filament::button wire:click="terapkanImpor" icon="heroicon-o-check" color="warning">
                        Terapkan {{ count($this->importPratinjau['diff']) }} Perubahan
                    </x-filament::button>
                @endif
                <x-filament::button wire:click="batalImpor" color="gray">Batal</x-filament::button>
            </div>
        </div>
    @endif

    {{-- Pencarian kebijakan --}}
    <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
        <x-filament::input
            type="search"
            wire:model.live.debounce.400ms="cari"
            placeholder="Cari kebijakan… (mis. izin, libur, THR, verifikasi)"
        />
    </x-filament::input.wrapper>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center justify-between gap-4">
            <p class="text-sm text-gray-500">
                Mencari penggantian istilah (mis. "Santri" → "Siswa")? Atur di
                <a href="{{ \App\Filament\Pages\PengaturanIstilah::getUrl() }}" class="text-primary-600 underline">Pengaturan Istilah</a>.
            </p>
            <x-filament::button type="submit" icon="heroicon-o-check">
                Simpan Kebijakan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
