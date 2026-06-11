<?php

namespace Modules\Keuangan\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Kelas;
use Modules\Keuangan\Models\Beasiswa;
use Modules\Keuangan\Models\Tagihan;
use RuntimeException;

/**
 * 2c — Beasiswa potong SPP dari dana sosial (zakat/infaq/wakaf).
 *
 * DESAIN PALING KECIL — TIDAK menyentuh math TagihanService:
 *  - Tagihan digenerate lewat jalur existing (TagihanService::buatTagihanPerKelas).
 *  - Potongan beasiswa diterapkan SETELAHNYA lewat mekanisme diskon yang SUDAH
 *    ADA di tabel tagihan (kolom diskon + nominal_akhir = max(nominal-diskon, 0),
 *    rumus identik dengan TagihanService::persistTagihan).
 *  - Tidak ada posting ledger di tahap ini: penagihan dicatat cash-basis —
 *    jurnal hanya terbit saat Pembayaran diverifikasi (PostingPembayaranListener).
 *    Dana donasi sudah dijurnal saat konfirmasi donasi; beasiswa mengurangi
 *    tagihan wali, bukan arus kas, sehingga tidak butuh jurnal tambahan.
 */
class BeasiswaService
{
    /**
     * Total potongan beasiswa aktif untuk santri pada periode bulan tertentu.
     */
    public function potonganUntuk(int $santriId, ?string $bulanMasehi = null): float
    {
        $tanggal = $bulanMasehi !== null
            ? Carbon::createFromFormat('Y-m', $bulanMasehi)->startOfMonth()
            : now()->startOfMonth();

        return (float) Beasiswa::query()
            ->where('santri_id', $santriId)
            ->where('aktif', true)
            ->whereDate('mulai', '<=', $tanggal->copy()->endOfMonth())
            ->where(fn ($q) => $q->whereNull('selesai')->orWhereDate('selesai', '>=', $tanggal))
            ->sum('nominal_per_bulan');
    }

    /**
     * Terapkan potongan beasiswa pada satu tagihan SPP — IDEMPOTEN.
     *
     * Porsi beasiswa dilacak di kolom sendiri (diskon_beasiswa) dan di-SET
     * (bukan ditambahkan ke diskon manual TU), sehingga re-run menghasilkan
     * angka yang sama, tidak menumpuk. nominal_akhir dihitung ulang di sini
     * (di LUAR math TagihanService): max(nominal - diskon - diskon_beasiswa, 0).
     *
     * Dilewati bila: bukan SPP, tagihan sudah dibayar (status != belum_bayar),
     * atau santri tidak berstatus aktif.
     */
    public function terapkanPada(Tagihan $tagihan): Tagihan
    {
        $this->ensureTenantContext();

        if ($tagihan->jenis_tagihan !== 'spp') {
            return $tagihan;
        }

        // Jangan sentuh tagihan yang sudah (sebagian) dibayar/lunas.
        if ($tagihan->status !== 'belum_bayar') {
            return $tagihan;
        }

        // Hanya santri aktif yang berhak potongan beasiswa.
        if ($tagihan->santri?->status !== 'aktif') {
            return $tagihan;
        }

        $potongan = $this->potonganUntuk((int) $tagihan->santri_id, $tagihan->bulan_masehi);

        $nominal = (float) $tagihan->nominal;
        $diskonManual = (float) $tagihan->diskon;

        // Dibatasi sisa setelah diskon manual agar nominal_akhir tak negatif.
        $diskonBeasiswa = min(max($potongan, 0), max($nominal - $diskonManual, 0));
        $nominalAkhir = max($nominal - $diskonManual - $diskonBeasiswa, 0);

        if ((float) $tagihan->diskon_beasiswa === $diskonBeasiswa
            && (float) $tagihan->nominal_akhir === $nominalAkhir) {
            return $tagihan; // sudah sesuai — no-op
        }

        $tagihan->forceFill([
            'diskon_beasiswa' => $diskonBeasiswa,
            'nominal_akhir' => $nominalAkhir,
        ])->save();

        return $tagihan->fresh();
    }

    /**
     * Jalur generate SPP per kelas + beasiswa: panggil jalur existing apa
     * adanya, lalu terapkan potongan. Atomik dalam satu transaksi.
     */
    public function buatTagihanSppPerKelas(Kelas|int $kelas, array $payload): EloquentCollection
    {
        $this->ensureTenantContext();

        return DB::transaction(function () use ($kelas, $payload): EloquentCollection {
            $tagihanList = app(TagihanService::class)->buatTagihanPerKelas($kelas, $payload);

            return $tagihanList->map(fn (Tagihan $tagihan) => $this->terapkanPada($tagihan));
        });
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk beasiswa.');
        }
    }
}
