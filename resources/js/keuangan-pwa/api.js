const BASE = '/keuangan/api'

async function request(path, options = {}) {
    const url = `${BASE}${path}`
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...options.headers,
    }

    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json'
        options.body = JSON.stringify(options.body)
    }

    const res = await fetch(url, { ...options, headers, credentials: 'same-origin' })

    if (!res.ok) {
        const error = new Error(`API ${res.status}: ${res.statusText}`)
        error.status = res.status
        try { error.data = await res.json() } catch { /* ignore */ }
        throw error
    }

    return res.json()
}

export const api = {
    get: (path) => request(path),
    post: (path, body) => request(path, { method: 'POST', body }),
}
