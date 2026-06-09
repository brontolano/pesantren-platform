<x-filament-panels::page>
    @php($p = $laporan['pendapatan'])
    <div class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Periode {{ \Illuminate\Support\Carbon::parse($laporan['periode']['from'])->translatedFormat('d M Y') }}
            – {{ \Illuminate\Support\Carbon::parse($laporan['periode']['to'])->translatedFormat('d M Y') }}
        </p>

        {{-- Status + % halal --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Persentase Halal</div>
                <div class="mt-1 text-3xl font-bold {{ $laporan['persen_halal'] >= 100 ? 'text-success-600' : 'text-warning-600' }}">
                    {{ number_format($laporan['persen_halal'], 2, ',', '.') }}%
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Status Dana</div>
                <div class="mt-2">
                    @if ($laporan['status'] === 'bersih')
                        <x-filament::badge color="success" size="lg">Bersih (100% halal)</x-filament::badge>
                    @else
                        <x-filament::badge color="warning" size="lg">Ada dana syubhat/non-halal</x-filament::badge>
                    @endif
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Saldo Dana Kebajikan</div>
                <div class="mt-1 text-2xl font-bold tracking-tight">
                    Rp {{ number_format((float) $laporan['dana_kebajikan'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-xs text-gray-400">dari dana syubhat/non-halal, disalurkan untuk kebajikan</div>
            </x-filament::section>
        </div>

        {{-- Rincian pendapatan per klasifikasi --}}
        <x-filament::section heading="Pendapatan per Klasifikasi Kehalalan">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="py-2 font-medium">Klasifikasi</th>
                        <th class="py-2 text-right font-medium">Nominal</th>
                        <th class="py-2 text-right font-medium">Porsi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach (['halal' => ['Halal', 'success'], 'syubhat' => ['Syubhat', 'warning'], 'non_halal' => ['Non-Halal', 'danger']] as $key => [$label, $color])
                        <tr>
                            <td class="py-2"><x-filament::badge :color="$color">{{ $label }}</x-filament::badge></td>
                            <td class="py-2 text-right font-medium whitespace-nowrap">Rp {{ number_format((float) $p[$key], 0, ',', '.') }}</td>
                            <td class="py-2 text-right text-gray-500">
                                {{ $p['total'] > 0 ? number_format($p[$key] / $p['total'] * 100, 1, ',', '.') : '0' }}%
                            </td>
                        </tr>
                    @endforeach
                    <tr class="font-semibold">
                        <td class="py-2">Total</td>
                        <td class="py-2 text-right whitespace-nowrap">Rp {{ number_format((float) $p['total'], 0, ',', '.') }}</td>
                        <td class="py-2 text-right">100%</td>
                    </tr>
                </tbody>
            </table>
            <p class="mt-3 text-xs text-gray-400">
                Sertifikat Kehalalan PDF menyusul (membutuhkan F8 ReportExport). Acuan: KehalalanGuard menegakkan no-mix saat posting.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
