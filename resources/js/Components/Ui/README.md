# Design System — Komponen UI Portal

> **Baca ini sebelum membuat UI baru.** Komponen di sini sudah bermerek (hijau/emas,
> ornamen islami). **Susun dari primitif ini — JANGAN tulis Tailwind mentah dari nol.**
> Itu yang membuat tampilan konsisten & tidak generik.
>
> Impor ringkas: `import { UiButton, StatCard, MoneyText } from '@/Components/Ui'`

## Kapan pakai apa

| Butuh | Komponen | Prop penting |
|---|---|---|
| Tombol aksi | `UiButton` | `variant` (primary\|secondary\|ghost…), `size`, `block`, `loading`, `iconLeft/iconRight`, `href`/`as` |
| Kartu wadah | `UiCard` · `SectionCard` | judul + slot konten |
| Angka uang | `MoneyText` | nilai rupiah terformat |
| Statistik ringkas | `StatCard` | `icon`, `tone` (emerald default), `label`, `value`, `hint`, `compact` |
| Status/label | `StatusBadge` · `Chip` | tone/varian |
| Input form | `UiField` (+ `UiInput`) | label + slot kontrol, pesan error |
| Judul halaman | `PageHeading` | judul + slot aksi |
| Baris daftar | `ListRow` | leading/trailing slot |
| Kosong/empty | `EmptyState` | ikon + pesan + aksi |
| Pilih metode | `MetodeCard` | kartu opsi terpilih |
| Sheet bawah | `BottomSheet` | modal mobile |
| Toast | `FlashToast` | notifikasi flash |
| Ikon dlm lingkaran | `IconCircle` | `icon`, tone |
| QR | `QrCanvas` | render QR |
| Ornamen islami | `IslamicPattern` · `MosqueSilhouette` · `OrnamentDivider` | dekorasi SVG, hero/pemisah |

Layout & navigasi lintas-portal ada di `resources/js/Shared/`:
`AppShell`, `BottomNav`, `HeroHeader`, `SegmentedControl`, `LoadingState`,
plus composable `useOnline`, `useOfflineForm`, dan `format.js`.

## Aturan komposisi
1. **Cari di tabel dulu.** Kalau komponennya ada → pakai. Kalau mirip → tambah `variant`/`tone`, jangan bikin komponen kembar.
2. **Token, bukan warna hardcode.** Pakai variabel merek (`--brand-grad`, `--brand-grad-hero`) & util emerald/amber yang sudah dipakai komponen, bukan hex acak.
3. **Komponen UI tidak fetch data.** Mereka presentational: terima `props`, pancarkan `emit`. Data datang dari composable (lihat `docs/KONVENSI.md`).
4. **Butuh primitif baru?** Tambahkan di sini + daftarkan di `index.js` + catat di tabel ini. Sekali tambah, semua portal dapat.

## Catatan
- Barrel awalnya untuk Portal Wali; primitif ini layak dipromosikan ke semua portal
  (Guru, Santri, PPDB). Saat menyentuh portal lain, impor dari sini dulu sebelum bikin baru.
