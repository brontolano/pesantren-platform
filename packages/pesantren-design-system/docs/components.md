# Komponen Blade

Komponen memakai namespace Blade pesantren-ui, misalnya x-pesantren-ui::button. Atribut HTML tambahan diteruskan ke elemen utama bila tersedia.

## Shell dan header

app-shell menyediakan sidebar desktop, slot navigasi ponsel, bar atas, area utama, dan tautan lompat konten.

~~~blade
<x-pesantren-ui::app-shell title="Pesantren.online">
    <x-slot:sidebar><nav aria-label="Navigasi desktop">...</nav></x-slot:sidebar>
    <x-slot:mobileNav><nav aria-label="Navigasi ponsel">...</nav></x-slot:mobileNav>
    <x-slot:topbar>...</x-slot:topbar>
    Isi halaman
</x-pesantren-ui::app-shell>
~~~

Isi sidebar dan mobileNav secara terpisah agar ponsel dapat memakai navigasi berbeda. Pastikan setiap id unik. page-header menerima judul, deskripsi, dan slot actions.

## Tombol, badge, kartu, formulir, status

~~~blade
<x-pesantren-ui::button variant="primary" type="submit">Simpan</x-pesantren-ui::button>
<x-pesantren-ui::button variant="secondary">Batal</x-pesantren-ui::button>
<x-pesantren-ui::badge tone="warning">Menunggu</x-pesantren-ui::badge>
<x-pesantren-ui::card><x-slot:header>Identitas</x-slot:header>Isi kartu</x-pesantren-ui::card>
<x-pesantren-ui::field label="Domain" name="domain" hint="pesantren.contoh.id" :error="$errors->first('domain')" required />
<x-pesantren-ui::status title="Draf tersimpan" tone="success" />
~~~

Varian tombol: primary, secondary, quiet, danger. Ukuran: sm, md, lg. Nada badge/status: neutral, info, success, warning, danger. Aplikasi tetap bertanggung jawab atas validasi server, izin, dan penyimpanan.

## Daftar data responsif

data-table menerima dua representasi data yang sama: tabel desktop dan kartu berlabel ponsel.

~~~blade
<x-pesantren-ui::data-table label="Organisasi">
    <x-slot:desktop>
        <table>
            <caption>Daftar organisasi</caption>
            <thead><tr><th scope="col">Nama</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @foreach ($organizations as $organization)
                    <tr><th scope="row">{{ $organization->name }}</th><td>{{ $organization->status_label }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </x-slot:desktop>
    <x-slot:mobile>
        <ul class="pu-data-table__mobile-list">
            @foreach ($organizations as $organization)
                <li class="pu-data-table__mobile-card">
                    <dl><dt>Nama</dt><dd>{{ $organization->name }}</dd><dt>Status</dt><dd>{{ $organization->status_label }}</dd></dl>
                    <a href="{{ route('organizations.show', $organization) }}">Lihat organisasi</a>
                </li>
            @endforeach
        </ul>
    </x-slot:mobile>
</x-pesantren-ui::data-table>
~~~

Gunakan koleksi serta kebijakan otorisasi yang sama pada kedua slot. Sel tabel desktop membungkus kata/domain panjang. Pilih kolom yang penting untuk keputusan; pindahkan informasi sekunder ke detail atau disclosure yang dapat diakses dengan keyboard. Tabel tetap memiliki area gulir fokusable sebagai fallback untuk tabel yang betul-betul lebar. Pada lebar 768 px ke bawah, stylesheet menampilkan kartu dan menyembunyikan tabel dari tampilan/pohon aksesibilitas. Jangan duplikasi data berbeda antar-slot. Server tetap memeriksa izin pada endpoint.
