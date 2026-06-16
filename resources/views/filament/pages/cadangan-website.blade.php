<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Website: {{ $namaPondok }}</x-slot>
            <x-slot name="description">Cadangan KONTEN WEBSITE saja — terpisah dari data santri/keuangan/admin.</x-slot>

            <div class="prose prose-sm max-w-none dark:prose-invert">
                <p>Halaman ini mencakup <strong>konten website (CMS)</strong>: artikel, galeri, profil, pengaturan website, beserta gambar-gambarnya. Data santri, keuangan, dan akun <strong>TIDAK termasuk</strong> — itu dikelola terpisah di menu <strong>Pengaturan → Cadangan &amp; Pemulihan</strong>.</p>
                <p class="text-sm"><strong>Catatan:</strong> identitas/branding (logo, warna, nama pondok) tersimpan di <em>Pengaturan</em>, jadi tercakup di cadangan <em>data</em> (internal), <strong>bukan</strong> di sini. Restore sebaiknya ke pondok yang sama.</p>
                <ul>
                    <li><strong>Backup Konten / Media Website</strong> — mengunduh cadangan website (aman, tidak mengubah apa pun).</li>
                    <li><strong>Restore Konten / Media Website</strong> — menimpa website dengan isi berkas cadangan. <em>Backup pengaman dibuat otomatis lebih dulu.</em></li>
                    <li><strong>Reset Website</strong> — mengosongkan seluruh konten website. <em>Backup pengaman dibuat otomatis lebih dulu; data operasional tetap utuh.</em></li>
                </ul>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">⚠️ Catatan penting</x-slot>
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <ul>
                    <li><strong>Restore</strong> dan <strong>Reset</strong> menimpa/menghapus <strong>konten website</strong> dan <strong>tidak bisa dibatalkan</strong>. Ketik nama pondok untuk konfirmasi.</li>
                    <li>Operasi di sini <strong>tidak menyentuh</strong> data santri, keuangan, presensi, atau akun — pemisahan sengaja (eksternal vs internal).</li>
                    <li>Lakukan <strong>Backup</strong> dulu sebelum operasi besar, dan simpan berkasnya sendiri sebagai jaring pengaman tambahan.</li>
                </ul>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
