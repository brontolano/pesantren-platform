<x-filament-panels::page>
    {{ $this->form }}

    @php($n = $this->neraca)
    @php($lr = $this->labaRugi)
    @php($ak = $this->arusKas)

    <div class="grid md:grid-cols-2 gap-6 mt-6">
        {{-- NERACA --}}
        <div class="rounded-lg border p-4">
            <h3 class="font-semibold mb-2">Neraca</h3>
            <table class="w-full text-sm">
                <tr class="font-semibold"><td>ASET</td><td class="text-right">{{ number_format($n['total_aset'], 2) }}</td></tr>
                @foreach ($n['aset'] as $r)
                    <tr><td class="pl-3 text-gray-600">{{ $r['kode'] }} {{ $r['nama'] }}</td><td class="text-right">{{ number_format($r['saldo'], 2) }}</td></tr>
                @endforeach
                <tr class="font-semibold border-t"><td>KEWAJIBAN + EKUITAS + Surplus</td><td class="text-right">{{ number_format($n['total_kewajiban_ekuitas'], 2) }}</td></tr>
                @foreach ($n['kewajiban'] as $r)
                    <tr><td class="pl-3 text-gray-600">{{ $r['kode'] }} {{ $r['nama'] }}</td><td class="text-right">{{ number_format($r['saldo'], 2) }}</td></tr>
                @endforeach
                @foreach ($n['ekuitas'] as $r)
                    <tr><td class="pl-3 text-gray-600">{{ $r['kode'] }} {{ $r['nama'] }}</td><td class="text-right">{{ number_format($r['saldo'], 2) }}</td></tr>
                @endforeach
                <tr><td class="pl-3 text-gray-600">Surplus/Defisit berjalan</td><td class="text-right">{{ number_format($n['surplus_berjalan'], 2) }}</td></tr>
            </table>
            <p class="mt-2 text-xs {{ $n['seimbang'] ? 'text-green-700' : 'text-red-700' }}">
                {{ $n['seimbang'] ? '✓ Neraca seimbang' : '✗ TIDAK seimbang' }}
            </p>
        </div>

        {{-- LABA RUGI --}}
        <div class="rounded-lg border p-4">
            <h3 class="font-semibold mb-2">Laba Rugi / Surplus-Defisit</h3>
            <table class="w-full text-sm">
                <tr class="font-semibold"><td>Pendapatan</td><td class="text-right">{{ number_format($lr['total_pendapatan'], 2) }}</td></tr>
                @foreach ($lr['pendapatan'] as $r)
                    <tr><td class="pl-3 text-gray-600">{{ $r['nama'] }}</td><td class="text-right">{{ number_format($r['saldo'], 2) }}</td></tr>
                @endforeach
                <tr class="font-semibold border-t"><td>Beban</td><td class="text-right">{{ number_format($lr['total_beban'], 2) }}</td></tr>
                @foreach ($lr['beban'] as $r)
                    <tr><td class="pl-3 text-gray-600">{{ $r['nama'] }}</td><td class="text-right">{{ number_format($r['saldo'], 2) }}</td></tr>
                @endforeach
                <tr class="font-semibold border-t-2"><td>Surplus / (Defisit)</td><td class="text-right">{{ number_format($lr['surplus_defisit'], 2) }}</td></tr>
            </table>

            <h3 class="font-semibold mt-4 mb-2">Arus Kas (ringkas)</h3>
            <table class="w-full text-sm">
                <tr><td>Saldo Awal</td><td class="text-right">{{ number_format($ak['saldo_awal'], 2) }}</td></tr>
                <tr><td>Kas Masuk</td><td class="text-right">{{ number_format($ak['total_masuk'], 2) }}</td></tr>
                <tr><td>Kas Keluar</td><td class="text-right">{{ number_format($ak['total_keluar'], 2) }}</td></tr>
                <tr class="font-semibold"><td>Saldo Akhir</td><td class="text-right">{{ number_format($ak['saldo_akhir'], 2) }}</td></tr>
            </table>
        </div>
    </div>
</x-filament-panels::page>
