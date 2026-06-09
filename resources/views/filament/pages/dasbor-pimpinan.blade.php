<x-filament-panels::page>
    @php($k = $this->kpi)
    <div class="grid md:grid-cols-3 gap-4">
        <div class="rounded-lg border p-4"><div class="text-gray-500 text-sm">Kas Konsolidasi</div><div class="text-2xl font-semibold">Rp {{ number_format($k['kas'], 0) }}</div></div>
        <div class="rounded-lg border p-4"><div class="text-gray-500 text-sm">Surplus / (Defisit)</div><div class="text-2xl font-semibold {{ $k['surplus'] >= 0 ? 'text-green-700' : 'text-red-700' }}">Rp {{ number_format($k['surplus'], 0) }}</div></div>
        <div class="rounded-lg border p-4"><div class="text-gray-500 text-sm">Tunggakan SPP</div><div class="text-2xl font-semibold text-amber-700">Rp {{ number_format($k['tunggakan'], 0) }}</div></div>
        <div class="rounded-lg border p-4"><div class="text-gray-500 text-sm">Total Pemasukan</div><div class="text-xl font-semibold">Rp {{ number_format($k['pendapatan'], 0) }}</div></div>
        <div class="rounded-lg border p-4"><div class="text-gray-500 text-sm">Total Pengeluaran</div><div class="text-xl font-semibold">Rp {{ number_format($k['beban'], 0) }}</div></div>
    </div>

    @php($dt = $this->danaTerikat)
    <div class="mt-6 grid md:grid-cols-2 gap-4">
        <div class="rounded-lg border p-4">
            <h3 class="font-semibold mb-2">Dana Wakaf (PSAK 112)</h3>
            @forelse ($dt['wakaf'] as $w)
                <div class="flex justify-between text-sm"><span>{{ $w['nama'] }}</span><span class="font-semibold">Rp {{ number_format($w['saldo'], 0) }}</span></div>
            @empty <div class="text-sm text-gray-400">—</div> @endforelse
        </div>
        <div class="rounded-lg border p-4">
            <h3 class="font-semibold mb-2">Dana ZIS (PSAK 109)</h3>
            @forelse ($dt['zis'] as $z)
                <div class="flex justify-between text-sm"><span>{{ $z['nama'] }}</span><span class="font-semibold">Rp {{ number_format($z['saldo'], 0) }}</span></div>
            @empty <div class="text-sm text-gray-400">—</div> @endforelse
        </div>
    </div>
</x-filament-panels::page>
