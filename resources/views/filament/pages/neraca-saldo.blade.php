<x-filament-panels::page>
    {{ $this->form }}

    @php($rows = $this->rows)
    @php($totalDebit = $rows->sum('debit'))
    @php($totalKredit = $rows->sum('kredit'))

    <div class="mt-6">
        <table class="w-full text-left text-sm border-collapse">
            <thead>
                <tr class="border-b">
                    <th class="py-1">Kode</th>
                    <th class="py-1">Nama Akun</th>
                    <th class="py-1">Tipe</th>
                    <th class="py-1 text-right">Debit</th>
                    <th class="py-1 text-right">Kredit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="border-b border-gray-100">
                        <td class="py-1">{{ $r['kode'] }}</td>
                        <td class="py-1">{{ $r['nama'] }}</td>
                        <td class="py-1">{{ $r['tipe'] }}</td>
                        <td class="py-1 text-right">{{ $r['debit'] ? number_format($r['debit'], 2) : '—' }}</td>
                        <td class="py-1 text-right">{{ $r['kredit'] ? number_format($r['kredit'], 2) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-2 text-gray-400">Belum ada saldo.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="font-semibold border-t-2">
                    <td colspan="3" class="py-1 text-right">Total</td>
                    <td class="py-1 text-right">Rp {{ number_format($totalDebit, 2) }}</td>
                    <td class="py-1 text-right">Rp {{ number_format($totalKredit, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="5" class="py-1 text-right text-xs {{ abs($totalDebit - $totalKredit) < 0.01 ? 'text-green-700' : 'text-red-700' }}">
                        {{ abs($totalDebit - $totalKredit) < 0.01 ? '✓ Seimbang (Σdebit = Σkredit)' : '✗ TIDAK seimbang — periksa jurnal' }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-filament-panels::page>
