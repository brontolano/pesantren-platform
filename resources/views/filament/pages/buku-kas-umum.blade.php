<x-filament-panels::page>
    {{ $this->form }}

    @php($h = $this->hasil)
    <div class="mt-6 space-y-2">
        <div class="grid grid-cols-3 gap-3 text-sm">
            <div class="rounded-lg border p-3"><div class="text-gray-500">Saldo Awal</div><div class="font-semibold">Rp {{ number_format($h['saldo_awal'], 2) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-gray-500">Total Masuk</div><div class="font-semibold text-green-700">Rp {{ number_format($h['total_masuk'], 2) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-gray-500">Total Keluar</div><div class="font-semibold text-red-700">Rp {{ number_format($h['total_keluar'], 2) }}</div></div>
        </div>

        <table class="w-full text-left text-sm border-collapse mt-2">
            <thead>
                <tr class="border-b">
                    <th class="py-1">Tanggal</th>
                    <th class="py-1">Nomor</th>
                    <th class="py-1">Deskripsi</th>
                    <th class="py-1 text-right">Masuk</th>
                    <th class="py-1 text-right">Keluar</th>
                    <th class="py-1 text-right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($h['mutasi'] as $m)
                    <tr class="border-b border-gray-100">
                        <td class="py-1">{{ \Illuminate\Support\Carbon::parse($m['tanggal'])->format('d M Y') }}</td>
                        <td class="py-1">{{ $m['nomor'] }}</td>
                        <td class="py-1">{{ $m['deskripsi'] }}</td>
                        <td class="py-1 text-right">{{ number_format($m['masuk'], 2) }}</td>
                        <td class="py-1 text-right">{{ number_format($m['keluar'], 2) }}</td>
                        <td class="py-1 text-right">{{ number_format($m['saldo'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-2 text-gray-400">Belum ada mutasi kas pada rentang ini.</td></tr>
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
</x-filament-panels::page>
