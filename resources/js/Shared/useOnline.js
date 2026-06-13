import { ref, onMounted, onBeforeUnmount } from 'vue'

/**
 * useOnline — status koneksi reaktif (untuk banner offline & antrian luring).
 * SSR-safe (default online=true sampai onMounted memeriksa navigator).
 *   const { online } = useOnline()
 */
export function useOnline() {
    const online = ref(true)

    function sync() {
        if (typeof navigator !== 'undefined') {
            online.value = navigator.onLine
        }
    }

    onMounted(() => {
        sync()
        if (typeof window !== 'undefined') {
            window.addEventListener('online', sync)
            window.addEventListener('offline', sync)
        }
    })

    onBeforeUnmount(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('online', sync)
            window.removeEventListener('offline', sync)
        }
    })

    return { online }
}
