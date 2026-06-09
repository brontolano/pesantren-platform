<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Piutang Santri</div>
            <div class="mt-1 text-2xl font-bold tracking-tight">Rp {{ number_format((float) $totalPiutang, 0, ',', '.') }}</div>
        </x-filament::section>

        <x-filament::section heading="Rincian per Santri (+ aging)">
            @if (empty($baris))
                <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    Belum ada piutang ber-tag pihak. Buku pembantu terisi otomatis setelah
                    jurnal pembayaran/tagihan di-tag party santri (rollout F4).
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4 font-medium">Santri</th>
                                <th class="py-2 pr-4 text-right font-medium">Saldo</th>
                                <th class="py-2 pr-4 text-right font-medium">0–30</th>
                                <th class="py-2 pr-4 text-right font-medium">31–60</th>
                                <th class="py-2 pr-4 text-right font-medium">61–90</th>
                                <th class="py-2 text-right font-medium">&gt;90</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($baris as $b)
                                <tr>
                                    <td class="py-2 pr-4">{{ $b['nama'] }}</td>
                                    <td class="py-2 pr-4 text-right font-medium whitespace-nowrap">Rp {{ number_format((float) $b['saldo'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-4 text-right text-gray-500">{{ number_format((float) $b['aging']['0-30'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-4 text-right text-gray-500">{{ number_format((float) $b['aging']['31-60'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-4 text-right text-gray-500">{{ number_format((float) $b['aging']['61-90'], 0, ',', '.') }}</td>
                                    <td class="py-2 text-right {{ $b['aging']['>90'] > 0 ? 'text-danger-600 font-medium' : 'text-gray-500' }}">{{ number_format((float) $b['aging']['>90'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
