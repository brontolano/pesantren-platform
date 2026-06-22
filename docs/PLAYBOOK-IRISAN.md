# Playbook: Membuat / Merapikan Satu "Irisan" (Vertical Slice)

> Resep yang diulang untuk tiap modul supaya tiap tugas hanya butuh **satu irisan
> kecil** konteks. Contoh kerja: **Keuangan → Portal Wali → Financial**.
> Acuan aturan: `KONVENSI.md`. Acuan besar: `../../BLUEPRINT-ARSITEKTUR-5-LAPISAN.md`.

## Berkas contoh (sudah dibuat, INERT — belum tersambung ke produksi)
| Lapisan | Berkas contoh |
|---|---|
| Backend kontrak (DTO) | `Modules/Keuangan/app/Data/{TagihanWaliData,PembayaranWaliData,WaliFinancialData}.php` |
| Kontrak FE (tipe) | `resources/js/types/wali.d.ts` |
| Frontend logic | `resources/js/composables/wali/useFinancial.js` |
| API layer | `resources/js/services/wali.js` |
| UI template | `resources/js/_reference/PortalWali.Financial.example.vue` (di luar glob `Pages/**` → tak ikut ter-build) |

Semua di atas **tidak mengubah perilaku apa pun** sampai Anda menyambungkannya di
langkah di bawah. Aman direview dulu.

---

## A. Menyambungkan pilot ini ke produksi (saat di tree yang bersih)

> Prasyarat: kerjakan di branch dari `origin/main` yang BERSIH (tree lokal saat ini
> 85 commit di depan origin/main + ada WIP — jangan menambah di atasnya).

### A1. Backend: kembalikan DTO, bukan array inline
Di `app/Services/PortalWali/PortalWaliService.php`, ganti map-array inline pada
`getFinancialPayload()` menjadi DTO:

```php
use Modules\Keuangan\Data\{TagihanWaliData, PembayaranWaliData, WaliFinancialData};

public function getFinancialPayload(Santri $santri): array
{
    $bills = Tagihan::query()->where('santri_id', $santri->getKey())
        ->with('pembayaran')->latest('jatuh_tempo')->get()
        ->map(fn (Tagihan $t) => TagihanWaliData::fromModel($t));

    $payments = Pembayaran::query()->where('santri_id', $santri->getKey())
        ->with('tagihan')->latest('tanggal_pembayaran')->get()
        ->map(fn (Pembayaran $p) => PembayaranWaliData::fromModel($p));

    $tabungan = /* … seperti sekarang … */;

    return (new WaliFinancialData(
        bills: $bills,
        payments: $payments,
        savings: [ 'saldo' => (float) ($tabungan?->saldo ?? 0), 'transactions' => /* … */ ],
        summary: [
            'tagihanBelumLunas' => $bills->whereIn('status', ['belum_bayar','sebagian'])->count(),
            'totalOutstanding'  => round($bills->sum('sisaTagihan'), 2),
        ],
    ))->toArray();
}
```
**Output JSON-nya identik** dengan sekarang (camelCase sama) → frontend lama tetap jalan.
Bedanya: bentuk data kini punya kontrak eksplisit & teruji.

### A2. Frontend: pindah ke 3-lapis
1. Salin `_reference/PortalWali.Financial.example.vue` → timpa `Pages/PortalWali/Financial.vue`.
2. Verifikasi prop `child/bills/payments/savings/summary` sama (sudah sama).
3. Pastikan komponen `Components/Ui` yang dipakai memang ada (lihat `Components/Ui/README.md`).

### A3. Verifikasi (WAJIB sebelum commit/deploy)
```
php artisan test --filter=PortalWali        # logika payload
npm run build                                # pastikan Vue contoh meng-compile
```
Tambah test kontrak ringkas (disarankan): assert key `bills[0]` = {id, jenisTagihan, …}.
Render-test panel authed (kalau menyentuh Filament): `TenantPanelAuthedSmokeTest`.

### A4. Rollback
Semua additif → `git checkout -- <berkas>` atau hapus folder `Data/`, `services/`,
`composables/`, `types/`, file `*.example.vue`. Tidak ada migrasi DB, tidak ada
perubahan dependency.

---

## B. Resep umum untuk irisan APA PUN (ulangi per modul)
1. **Kontrak dulu.** Tulis `*Data.php` (backend) + `{modul}.d.ts` (frontend) untuk
   tiap payload. Sepakati ini sebelum implementasi.
2. **Backend** mengisi DTO di Service; Controller `return $data->toArray()`.
3. **Service FE** (`services/{portal}.js`): bungkus semua `router.post/get`.
4. **Composable** (`composables/{portal}/use{Fitur}.js`): state + aturan + aksi.
5. **Page .vue**: template murni, susun dari `Components/Ui`.
6. **Verifikasi** (A3) → commit kecil per irisan.

Konteks satu tugas = folder `Modules/{X}/` + `{modul}.d.ts` + `Components/Ui/README.md`.

---

## C. Upgrade opsional — otomatisasi kontrak (Fase 0)
Setelah pola terbukti, hilangkan penulisan `.d.ts` manual:
```
composer require spatie/laravel-data spatie/typescript-transformer
```
- `*Data` cukup `extends Spatie\LaravelData\Data` → buang `toArray()` manual.
- Tandai dengan `#[TypeScript]`, lalu `php artisan typescript:transform`
  meng-generate `resources/js/types/generated.d.ts` otomatis dari DTO.
- Backend jadi satu-satunya sumber; frontend selalu sinkron.

## D. Filament masuk modul (langkah terpisah, lebih berisiko)
Pindah `app/Filament/{Resources,Pages}/<X>*` → `Modules/<X>/app/Filament/...`, lalu
daftarkan path di `AdminPanelProvider` (atau ServiceProvider modul):
```php
->discoverResources(in: base_path('Modules/Keuangan/app/Filament/Resources'),
                    for: 'Modules\\Keuangan\\Filament\\Resources')
->discoverPages(in: base_path('Modules/Keuangan/app/Filament/Pages'),
                for: 'Modules\\Keuangan\\Filament\\Pages')
```
**WAJIB** jalankan `TenantPanelAuthedSmokeTest` sesudahnya (panel authed mudah 500
saat resource/page dipindah). Lakukan satu modul per commit.
