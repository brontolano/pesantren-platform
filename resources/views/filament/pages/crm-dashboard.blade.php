<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Funnel Pendaftaran</x-slot>
            <table class="w-full text-sm">
                <tbody>
                    @forelse ($funnel as $status => $jml)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 capitalize text-gray-600 dark:text-gray-300">{{ str_replace('_', ' ', $status ?? 'tanpa status') }}</td>
                            <td class="py-2 text-right font-bold text-primary-600">{{ $jml }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-2 text-gray-400">Belum ada data pendaftaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Santri Berisiko Churn (tunggakan)</x-slot>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-400">
                        <th class="py-1">Santri</th>
                        <th class="py-1 text-right">Tunggakan</th>
                        <th class="py-1 text-right">Hadir %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($churn as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-1">{{ $row['nama'] ?? ('#'.$row['santri_id']) }}</td>
                            <td class="py-1 text-right font-medium text-danger-600">Rp {{ number_format($row['tunggakan'], 0, ',', '.') }}</td>
                            <td class="py-1 text-right">{{ $row['kehadiran'] !== null ? number_format($row['kehadiran'], 0).'%' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-2 text-success-600">Tidak ada santri menunggak. 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    </div>

    <p class="mt-4 text-xs text-gray-400">
        Funnel dihitung dari status pendaftaran CalonSantri; churn dari sisa Tagihan belum lunas.
        Campaign re-enrollment & quotation tersedia di menu CRM terkait.
    </p>
</x-filament-panels::page>
