let registrationPromise = null;

export function registerWaliPwa() {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return Promise.resolve(null);
    }

    if (!window.location.pathname.startsWith('/wali')) {
        return Promise.resolve(null);
    }

    if (registrationPromise) {
        return registrationPromise;
    }

    const performRegistration = () => navigator.serviceWorker
        .register('/wali/sw.js', { scope: '/wali/' })
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
