// Helper format bersama (Rupiah & tanggal) — satu sumber kebenaran lintas PWA.
// Render uang di template sebaiknya via <MoneyText>; gunakan fungsi ini untuk
// string mentah (mis. judul, ekspor, payload).

const _rp = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
})

const _rpFull = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})

const _tanggal = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
})

/** Rupiah tanpa desimal: 1500000 -> "Rp 1.500.000". Aman untuk null/NaN. */
export function formatRupiah(value) {
    return _rp.format(Number(value || 0))
}

/** Rupiah dengan 2 desimal (mis. laporan akuntansi). */
export function formatRupiahFull(value) {
    return _rpFull.format(Number(value || 0))
}

/**
 * Tanggal panjang id-ID: "13 Juni 2026".
 * CATATAN: tanggal yang SUDAH diformat backend dipakai apa adanya —
 * fungsi ini hanya untuk Date/ISO mentah; string non-tanggal dikembalikan utuh.
 */
export function formatTanggal(value) {
    if (!value) return '-'
    const dt = value instanceof Date ? value : new Date(value)
    return Number.isNaN(dt.getTime()) ? String(value) : _tanggal.format(dt)
}
