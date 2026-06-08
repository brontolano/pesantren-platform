<x-filament-panels::page>
    {{ $this->form }}

    @php($rows = $this->rows)
    <div class="mt-6">
        <table class="w-full text-left text-sm border-collapse">
            <thead>
                <tr class="border-b">
                    <th class="py-1">Kode</th>
                    <th class="py-1">Jenis Pajak</th>
                    <th class="py-1 text-right">Dipungut/Dipotong</th>
                    <th class="py-1 text-right">Disetor</th>
                    <th class="py-1 text-right">Saldo (belum disetor)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="border-b border-gray-100">
                        <td class="py-1">{{ $r['kode'] }}</td>
                        <td class="py-1">{{ $r['nama'] }}</td>
                        <td class="py-1 text-right">{{ number_format($r['dipungut'], 2) }}</td>
                        <td class="py-1 text-right">{{ number_format($r['disetor'], 2) }}</td>
                        <td class="py-1 text-right font-semibold">{{ number_format($r['saldo'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-2 text-gray-400">Belum ada transaksi pajak pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-3 text-xs text-gray-500">⚠ Aturan pajak (PMK 59/2022) menunggu review penasihat pajak sebelum rilis — Gate kepatuhan.</p>
    </div>
</x-filament-panels::page>
