<x-filament-panels::page>
    {{ $this->form }}
    @php($d = $this->dana)
    @php($blok = ['wakaf' => 'Dana Wakaf (PSAK 112)', 'zis' => 'Dana ZIS (PSAK 109)', 'lainnya_terikat' => 'Dana Terikat Lainnya'])
    <div class="mt-6 space-y-6">
        @foreach ($blok as $key => $judul)
            <div>
                <h3 class="font-semibold mb-1">{{ $judul }}</h3>
                <table class="w-full text-left text-sm border-collapse">
                    <thead><tr class="border-b"><th class="py-1">Kode</th><th class="py-1">Sumber</th><th class="py-1 text-right">Penerimaan</th><th class="py-1 text-right">Penggunaan</th><th class="py-1 text-right">Saldo</th></tr></thead>
                    <tbody>
                        @forelse ($d[$key] as $r)
                            <tr class="border-b border-gray-100">
                                <td class="py-1">{{ $r['kode'] }}</td>
                                <td class="py-1">{{ $r['nama'] }}</td>
                                <td class="py-1 text-right">{{ number_format($r['penerimaan'], 2) }}</td>
                                <td class="py-1 text-right">{{ number_format($r['penggunaan'], 2) }}</td>
                                <td class="py-1 text-right font-semibold">{{ number_format($r['saldo'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-2 text-gray-400">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
        <p class="text-xs text-gray-500">Dana terikat tidak boleh dipakai di luar peruntukannya (PSAK 112/109). Dana wakaf terkunci dari agunan (UU 41/2004).</p>
    </div>
</x-filament-panels::page>
