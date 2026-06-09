<x-filament-panels::page>
    @php($cl = $this->checklist)
    @php($op = $this->operasional)

    <div class="grid md:grid-cols-2 gap-6">
        <div class="rounded-lg border p-4">
            <h3 class="font-semibold mb-2">L4 — Checklist Kesiapan Asesmen (SYAMIL)</h3>
            <table class="w-full text-sm">
                <thead><tr class="border-b"><th class="text-left py-1">Dokumen</th><th class="text-left py-1">Regulasi</th><th class="py-1">Status</th></tr></thead>
                <tbody>
                    @forelse ($cl as $d)
                        <tr class="border-b border-gray-100">
                            <td class="py-1">{{ $d['jenis'] }} — {{ $d['judul'] }}</td>
                            <td class="py-1 text-gray-500">{{ $d['regulasi'] ?? '—' }}</td>
                            <td class="py-1 text-center">{!! $d['lengkap'] ? '<span class="text-green-700">✓</span>' : '<span class="text-amber-700">belum</span>' !!}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-2 text-gray-400">Belum ada dokumen mutu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-lg border p-4">
            <h3 class="font-semibold mb-2">L7 — Profil Operasional</h3>
            <table class="w-full text-sm">
                <tr><td>Total Pendapatan</td><td class="text-right">Rp {{ number_format($op['pendapatan'], 0) }}</td></tr>
                <tr><td>Total Beban</td><td class="text-right">Rp {{ number_format($op['beban'], 0) }}</td></tr>
                <tr class="font-semibold"><td>Surplus / (Defisit)</td><td class="text-right">Rp {{ number_format($op['surplus'], 0) }}</td></tr>
                <tr><td>Tunggakan SPP</td><td class="text-right">Rp {{ number_format($op['tunggakan'], 0) }}</td></tr>
            </table>
            <h4 class="font-semibold mt-3 mb-1 text-sm">Dana Terikat (PSAK 112/109)</h4>
            @foreach (['wakaf' => 'Wakaf', 'zis' => 'ZIS'] as $k => $label)
                @foreach ($op['dana_terikat'][$k] as $r)
                    <div class="flex justify-between text-sm"><span>{{ $label }} — {{ $r['nama'] }}</span><span>Rp {{ number_format($r['saldo'], 0) }}</span></div>
                @endforeach
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
