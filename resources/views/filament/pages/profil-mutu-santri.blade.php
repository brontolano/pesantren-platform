<x-filament-panels::page>
    <x-filament::section>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Santri</label>
        <select wire:model.live="santriId"
                class="fi-input mt-1 block w-full max-w-md rounded-lg border-gray-300 text-sm shadow-sm dark:bg-gray-800 dark:border-gray-600">
            <option value="">— pilih santri —</option>
            @foreach ($santris as $s)
                <option value="{{ $s->id }}">{{ $s->nama }} ({{ $s->nis }})</option>
            @endforeach
        </select>
    </x-filament::section>

    @if ($santri && $profil)
        {{-- Lampu kuning --}}
        <x-filament::section>
            <x-slot name="heading">Lampu Kuning</x-slot>
            @if (count($lampu) === 0)
                <p class="text-sm text-success-600">✔ Tidak ada lampu kuning — alhamdulillah.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($lampu as $l)
                        <li class="rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-800 dark:bg-warning-500/10 dark:text-warning-400">
                            🟡 <span class="font-semibold">{{ $l['label'] }}</span>
                            <span class="text-xs text-gray-500">({{ $l['jenis'] }})</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-filament::section>
                <x-slot name="heading">Nilai</x-slot>
                <div class="text-3xl font-bold text-primary-600">
                    {{ $profil['nilai']['rata'] !== null ? number_format($profil['nilai']['rata'], 1) : '—' }}
                </div>
                <p class="text-sm text-gray-500">Rata-rata {{ $profil['nilai']['jumlah_mapel'] }} mapel
                    ({{ $profil['periode']['nama'] ?? 'belum ada periode' }})</p>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Disiplin</x-slot>
                <div class="text-3xl font-bold {{ $profil['disiplin']['total_poin'] > 0 ? 'text-danger-600' : 'text-success-600' }}">
                    {{ $profil['disiplin']['total_poin'] }} poin
                </div>
                @if ($profil['disiplin']['ambang_terdekat'])
                    <p class="text-sm text-gray-500">Ambang terdekat:
                        {{ $profil['disiplin']['ambang_terdekat']['label'] }} = {{ $profil['disiplin']['ambang_terdekat']['poin'] }} poin
                        ({{ $profil['disiplin']['persen_ambang'] }}%)</p>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">UKS</x-slot>
                <div class="text-3xl font-bold text-info-600">{{ $profil['uks']['jumlah'] }}×</div>
                <p class="text-sm text-gray-500">Kunjungan periode ini —
                    terakhir: {{ $profil['uks']['terakhir'] ?? 'tidak ada' }}</p>
            </x-filament::section>
        </div>

        {{-- Tren hafalan: bar angka sederhana (tanpa lib chart) --}}
        <x-filament::section>
            <x-slot name="heading">Tren Hafalan ({{ count($profil['hafalan']['per_pekan']) }} pekan terakhir)</x-slot>
            <table class="w-full text-center text-sm">
                <tbody>
                    <tr>
                        @foreach ($profil['hafalan']['per_pekan'] as $p)
                            <td class="px-1 align-bottom">
                                <div class="mx-auto w-6 rounded-t bg-primary-500"
                                     style="height: {{ min(80, $p['jumlah'] * 16) }}px"></div>
                            </td>
                        @endforeach
                    </tr>
                    <tr class="font-semibold">
                        @foreach ($profil['hafalan']['per_pekan'] as $p)
                            <td class="px-1 pt-1">{{ $p['jumlah'] }}</td>
                        @endforeach
                    </tr>
                    <tr class="text-xs text-gray-500">
                        @foreach ($profil['hafalan']['per_pekan'] as $p)
                            <td class="px-1">{{ \Illuminate\Support\Carbon::parse($p['mulai'])->format('d/m') }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
            <p class="mt-2 text-sm text-gray-500">Setoran terakhir: {{ $profil['hafalan']['terakhir'] ?? 'belum ada' }}</p>
        </x-filament::section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Kehadiran</x-slot>
                <table class="w-full text-sm">
                    <tbody>
                        <tr>
                            <td class="py-1 text-gray-500">Kelas</td>
                            <td class="py-1 text-right font-medium">
                                {{ $profil['kehadiran']['kelas']['persen'] !== null ? $profil['kehadiran']['kelas']['persen'].'%' : '—' }}
                                <span class="text-xs text-gray-400">({{ $profil['kehadiran']['kelas']['hadir'] }}/{{ $profil['kehadiran']['kelas']['hari_efektif'] }} hari efektif, alpa {{ $profil['kehadiran']['kelas']['alpa'] }})</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-1 text-gray-500">Kegiatan</td>
                            <td class="py-1 text-right font-medium">
                                {{ $profil['kehadiran']['kegiatan']['persen'] !== null ? $profil['kehadiran']['kegiatan']['persen'].'%' : '—' }}
                                <span class="text-xs text-gray-400">({{ $profil['kehadiran']['kegiatan']['hadir'] }}/{{ $profil['kehadiran']['kegiatan']['total'] }})</span>
                            </td>
                        </tr>
                        @forelse ($profil['kehadiran']['asrama'] as $slot => $a)
                            <tr>
                                <td class="py-1 text-gray-500">Asrama — {{ $slot }}</td>
                                <td class="py-1 text-right font-medium">
                                    {{ $a['persen'] !== null ? $a['persen'].'%' : '—' }}
                                    <span class="text-xs text-gray-400">({{ $a['hadir'] }}/{{ $a['total'] }})</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="py-1 text-gray-400">Belum ada presensi asrama.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Capaian Kitab</x-slot>
                <table class="w-full text-sm">
                    <tbody>
                        @forelse ($profil['kitab'] as $k)
                            <tr>
                                <td class="py-1 text-gray-500">{{ $k['kitab'] }}</td>
                                <td class="py-1 text-right font-medium">{{ $k['posisi'] ?? '-' }}
                                    <span class="text-xs text-gray-400">({{ $k['status'] }}, {{ $k['tanggal'] }})</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-1 text-gray-400">Belum ada capaian kitab.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-filament::section>
        </div>
    @elseif ($santriId)
        <x-filament::section>
            <p class="text-sm text-gray-500">Santri tidak ditemukan.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
