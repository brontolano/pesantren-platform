/**
 * LAPISAN SERVICE — Portal Wali.
 *
 * Satu-satunya tempat yang "berbicara" ke endpoint. Halaman .vue dan composable
 * TIDAK boleh memanggil router/fetch langsung — mereka memanggil fungsi di sini.
 * URL di bawah persis mengikuti routes/tenant.php (grup name `tenant.portal-wali.`).
 *
 * Lihat docs/KONVENSI.md §2 (".vue tidak boleh fetch langsung").
 */
import { router } from '@inertiajs/vue3'

const base = (santriId) => `/wali/santri/${santriId}`

/**
 * Bayar satu tagihan.  POST tenant.portal-wali.pembayaran.store
 * @param {number} santriId
 * @param {number} tagihanId
 * @param {{ nominal: number, metode: string, nomor_bukti?: string }} payload
 * @param {import('@inertiajs/core').VisitOptions} [options]
 */
export function bayarTagihan(santriId, tagihanId, payload, options = {}) {
  return router.post(`${base(santriId)}/tagihan/${tagihanId}/bayar`, payload, {
    preserveScroll: true,
    ...options,
  })
}

/**
 * Topup tabungan santri.  POST tenant.portal-wali.tabungan.topup
 * @param {number} santriId
 * @param {{ nominal: number, metode?: string, keterangan?: string }} payload
 * @param {import('@inertiajs/core').VisitOptions} [options]
 */
export function topupTabungan(santriId, payload, options = {}) {
  return router.post(`${base(santriId)}/tabungan/topup`, payload, {
    preserveScroll: true,
    ...options,
  })
}
