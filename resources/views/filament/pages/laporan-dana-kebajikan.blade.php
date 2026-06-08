<x-filament-panels::page>
    {{ $this->form }}
    @php($l = $this->laporan)
    <div class="mt-6 space-y-4">
        <div class="grid grid-cols-3 gap-3 text-sm">
            <div class="rounded-lg border p-3"><div class="text-gray-500">Penerimaan</div><div class="font-semibold">Rp {{ number_format($l['penerimaan'], 2) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-gray-500">Total Penyaluran</div><div class="font-semibold">Rp {{ number_format($l['total_penyaluran'], 2) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-gray-500">Saldo Belum Disalurkan</div><div class="font-semibold">Rp {{ number_format($l['saldo'], 2) }}</div></div>
        </div>

        <div>
            <h3 class="font-semibold mb-1 text-sm">Penyaluran per Peruntukan</h3>
            <table class="w-full text-left text-sm border-collapse">
                <thead><tr class="border-b"><th class="py-1">Akun</th><th class="py-1">Peruntukan</th><th class="py-1 text-right">Jumlah</th></tr></thead>
                <tbody>
                    @forelse ($l['penyaluran'] as $p)
                        <tr class="border-b border-gray-100">
                            <td class="py-1">{{ $p['kode'] }} {{ $p['nama'] }}</td>
                            <td class="py-1">{{ $p['peruntukan'] }}</td>
                            <td class="py-1 text-right">{{ number_format($p['jumlah'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-2 text-gray-400">Belum ada penyaluran.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="mt-3 text-xs text-gray-500">Dana ini tidak menambah surplus/aset neto pesantren. Niat = pelepasan (bukan sedekah). Dilarang untuk sarana ibadah.</p>
        </div>
    </div>
</x-filament-panels::page>
