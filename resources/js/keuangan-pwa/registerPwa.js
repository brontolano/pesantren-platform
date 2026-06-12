export async function registerKeuanganPwa() {
    if (!('serviceWorker' in navigator)) {
        return
    }

    try {
        const reg = await navigator.serviceWorker.register('/keuangan/sw.js', {
            scope: '/keuangan/',
        })
        reg.addEventListener('updatefound', () => {
            const newWorker = reg.installing
            if (!newWorker) return
            newWorker.addEventListener('statechange', () => {
                if (newWorker.state === 'activated' && navigator.serviceWorker.controller) {
                    console.info('[keuangan-pwa] SW updated — reload for latest shell.')
                }
            })
        })
    } catch (err) {
        console.warn('[keuangan-pwa] SW registration failed:', err)
    }
}
