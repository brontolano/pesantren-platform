<x-filament-panels::page>
    {{ $this->form }}

    @php($h = $this->hasil)
    @if ($h)
        <div class="mt-6 space-y-2">
            <div class="text-lg font-semibold">{{ $h['akun']->kode }} — {{ $h['akun']->nama }}</div>
            <div class="text-sm text-gray-500">Saldo awal: Rp {{ number_format($h['saldo_awal'], 2) }}</div>

            <table class="w-full text-left text-sm border-collapse mt-2">
                <thead>
                    <tr class="border-b">
                        <th class="py-1">Tanggal</th>
                        <th class="py-1">Nomor</th>
                        <th class="py-1">Deskripsi</th>
                        <th class="py-1 text-right">Debit</th>
                        <th class="py-1 text-right">Kredit</th>
                        <th class="py-1 text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($h['mutasi'] as $m)
                        <tr class="border-b border-gray-100">
                            <td class="py-1">{{ \Illuminate\Support\Carbon::parse($m['tanggal'])->format('d M Y') }}</td>
                            <td class="py-1">{{ $m['nomor'] }}</td>
                            <td class="py-1">{{ $m['deskripsi'] }}</td>
                            <td class="py-1 text-right">{{ number_format($m['debit'], 2) }}</td>
                            <td class="py-1 text-right">{{ number_format($m['kredit'], 2) }}</td>
                            <td class="py-1 text-right">{{ number_format($m['saldo'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-2 text-gray-400">Belum ada mutasi pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="font-semibold">
                        <td colspan="5" class="py-1 text-right">Saldo akhir</td>
                        <td class="py-1 text-right">Rp {{ number_format($h['saldo_akhir'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="mt-6 text-gray-400 text-sm">Pilih akun untuk menampilkan buku besar.</div>
    @endif
</x-filament-panels::page>
