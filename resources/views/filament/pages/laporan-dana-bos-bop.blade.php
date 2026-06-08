<x-filament-panels::page>
    {{ $this->form }}
    @php($rows = $this->rows)
    <div class="mt-6">
        <table class="w-full text-left text-sm border-collapse">
            <thead>
                <tr class="border-b">
                    <th class="py-1">Kode</th>
                    <th class="py-1">Sumber Dana</th>
                    <th class="py-1">Rezim</th>
                    <th class="py-1 text-right">Penerimaan</th>
                    <th class="py-1 text-right">Penggunaan</th>
                    <th class="py-1 text-right">Sisa</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="border-b border-gray-100">
                        <td class="py-1">{{ $r['kode'] }}</td>
                        <td class="py-1">{{ $r['nama'] }}</td>
                        <td class="py-1">{{ $r['rezim'] ?? '—' }}</td>
                        <td class="py-1 text-right">{{ number_format($r['penerimaan'], 2) }}</td>
                        <td class="py-1 text-right">{{ number_format($r['penggunaan'], 2) }}</td>
                        <td class="py-1 text-right font-semibold">{{ number_format($r['sisa'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-2 text-gray-400">Belum ada transaksi dana negara.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-3 text-xs text-gray-500">BOS Madrasah & BOP Pesantren disajikan terpisah (rezim). Barang dana BOS dikecualikan PPh 22 (dijamin mesin pajak).</p>
    </div>
</x-filament-panels::page>
