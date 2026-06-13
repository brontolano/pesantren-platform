import { ref } from 'vue'
import { useOnline } from './useOnline.js'

/**
 * useOfflineForm — membungkus pola submit anti-double-post + fallback antrian luring,
 * agar tak diduplikasi di tiap form lapangan (Kasir).
 *
 * Pola: coba kirim → bila offline ATAU gagal sementara → enqueue (antrian IndexedDB
 * idempoten client_ref milik offline-sync.js). Error PERMANEN (4xx validasi) dilempar,
 * TIDAK di-enqueue (agar tak retry selamanya).
 *
 * opts:
 *   submit:           async (payload) => result   — kirim ke server
 *   enqueue:          async (payload) => void      — simpan ke antrian luring (opsional)
 *   isPermanentError: (err) => boolean             — true jika error tak boleh di-antri
 *
 * return: { submitting, online, submitForm }
 *   submitForm(payload) -> { queued: boolean, result?, error? }
 *
 * CATATAN: composable ini TIDAK menggantikan engine offline-sync.js — ia memanggilnya.
 */
export function useOfflineForm({ submit, enqueue = null, isPermanentError = null } = {}) {
    const { online } = useOnline()
    const submitting = ref(false)

    async function submitForm(payload) {
        submitting.value = true
        try {
            if (!online.value && enqueue) {
                await enqueue(payload)
                return { queued: true }
            }
            const result = await submit(payload)
            return { queued: false, result }
        } catch (err) {
            const permanent = typeof isPermanentError === 'function' && isPermanentError(err)
            if (enqueue && !permanent) {
                await enqueue(payload)
                return { queued: true, error: err }
            }
            throw err
        } finally {
            submitting.value = false
        }
    }

    return { submitting, online, submitForm }
}
