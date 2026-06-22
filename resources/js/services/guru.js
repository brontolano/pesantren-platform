/**
 * LAPISAN SERVICE — Portal Guru. Satu-satunya pintu ke endpoint guru.
 * URL mengikuti routes/tenant.php grup `tenant.portal-guru.` (prefix /guru).
 * Halaman .vue & composable TIDAK boleh router/fetch langsung — panggil fungsi ini.
 */
import { router } from '@inertiajs/vue3'

/**
 * Simpan presensi kelas.  POST tenant.portal-guru.presensi.store
 * @param {number} jadwalId
 * @param {import('@/types/guru').PresensiSubmit} payload
 * @param {import('@inertiajs/core').VisitOptions} [options]
 */
export function submitPresensi(jadwalId, payload, options = {}) {
  return router.post(`/guru/presensi/${jadwalId}`, payload, {
    preserveScroll: true,
    ...options,
  })
}
