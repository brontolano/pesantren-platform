let registrationPromise = null;

export function registerSpmbPwa() {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return Promise.resolve(null);
    }

    if (!window.location.pathname.startsWith('/spmb')) {
        return Promise.resolve(null);
    }

    if (registrationPromise) {
        return registrationPromise;
    }

    const performRegistration = () => navigator.serviceWorker
        .register('/spmb/sw.js', { scope: '/spmb/' })
        .catch(() => null);

    registrationPromise = new Promise((resolve) => {
        const run = () => resolve(performRegistration());

        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(run, { timeout: 2000 });

            return;
        }

        window.setTimeout(run, 0);
    });

    return registrationPromise;
}
