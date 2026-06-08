{{-- Detail baris jurnal (read-only). Dipanggil dari JurnalResource aksi "Detail". --}}
<div class="space-y-3 text-sm">
    <div class="grid grid-cols-2 gap-2">
        <div><span class="font-semibold">Nomor:</span> {{ $jurnal->nomor }}</div>
        <div><span class="font-semibold">Tanggal:</span> {{ $jurnal->tanggal?->format('d M Y') }}</div>
        <div class="col-span-2"><span class="font-semibold">Deskripsi:</span> {{ $jurnal->deskripsi }}</div>
        <div><span class="font-semibold">Status:</span> {{ ucfirst($jurnal->status) }}</div>
        <div><span class="font-semibold">Sumber:</span> {{ $jurnal->tipe_sumber }}</div>
    </div>

    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="border-b">
                <th class="py-1">Akun</th>
                <th class="py-1">Divisi</th>
                <th class="py-1">Sumber Dana</th>
                <th class="py-1 text-right">Debit</th>
                <th class="py-1 text-right">Kredit</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($jurnal->baris as $baris)
                <tr class="border-b border-gray-100">
                    <td class="py-1">{{ $baris->akun?->kode }} — {{ $baris->akun?->nama }}</td>
                    <td class="py-1">{{ $baris->divisi?->nama_divisi ?? '—' }}</td>
                    <td class="py-1">{{ $baris->sumberDana?->nama_sumber ?? '—' }}</td>
                    <td class="py-1 text-right">{{ number_format((float) $baris->debit, 2) }}</td>
                    <td class="py-1 text-right">{{ number_format((float) $baris->kredit, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-semibold">
                <td class="py-1" colspan="3">Total</td>
                <td class="py-1 text-right">{{ number_format((float) $jurnal->baris->sum('debit'), 2) }}</td>
                <td class="py-1 text-right">{{ number_format((float) $jurnal->baris->sum('kredit'), 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
