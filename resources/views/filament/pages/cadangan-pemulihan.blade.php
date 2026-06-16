<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Pondok: {{ $namaPondok }}</x-slot>
            <x-slot name="description">Ukuran database saat ini: <strong>{{ $ukuranDb ?? 'tidak diketahui' }}</strong></x-slot>

            <div class="prose prose-sm max-w-none dark:prose-invert">
                <p>Halaman ini hanya untuk <strong>Super Admin pondok</strong>. Semua tombol bekerja pada data pondok <em>ini saja</em>.</p>
                <ul>
                    <li><strong>Backup Database / File</strong> — mengunduh cadangan data Anda (aman, tidak mengubah apa pun). Simpan berkasnya di tempat aman; berisi data pribadi santri.</li>
                    <li><strong>Restore Database / File</strong> — menimpa data saat ini dengan isi berkas cadangan. <em>Backup pengaman dibuat otomatis lebih dulu.</em></li>
                    <li><strong>Reset Seluruh Data</strong> — mengosongkan semua data dan mengembalikan ke kondisi awal. <em>Backup pengaman dibuat otomatis lebih dulu.</em></li>
                </ul>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">⚠️ Penting sebelum Restore / Reset</x-slot>
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <ul>
                    <li><strong>Restore</strong> dan <strong>Reset</strong> bersifat <strong>menimpa/menghapus</strong> dan <strong>tidak bisa dibatalkan</strong>. Anda harus mengetik nama pondok untuk konfirmasi.</li>
                    <li>Setelah <strong>Reset</strong>, Anda otomatis <strong>keluar</strong> dan tinggal <strong>login ulang dengan email &amp; kata sandi Anda yang biasa</strong> (akun Anda dipertahankan, hanya datanya yang dikosongkan).</li>
                    <li>Lakukan <strong>Backup</strong> dulu sebelum operasi besar, dan simpan berkasnya sendiri sebagai jaring pengaman tambahan.</li>
                </ul>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
