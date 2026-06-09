<x-filament-panels::page>
    {{ $this->form }}
    @php($rows = $this->rows)
    <div class="mt-6">
        <table class="w-full text-left text-sm border-collapse">
            <thead><tr class="border-b"><th class="py-1">Divisi / Unit</th><th class="py-1 text-right">Realisasi Beban</th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="border-b border-gray-100">
                        <td class="py-1">{{ $r['divisi'] }}</td>
                        <td class="py-1 text-right">{{ number_format($r['realisasi'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="py-2 text-gray-400">Belum ada realisasi beban.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-3 text-xs text-gray-500">Realisasi = akumulasi beban dari jurnal (auditable). Pagu/RKAM per komponen menyusul (tabel anggaran).</p>
    </div>
</x-filament-panels::page>
