/**
 * Mesin offline-sync PWA Keuangan (KEU-A03).
 * Antrian transaksi di IndexedDB → kirim ke server saat online, idempoten via client_ref.
 * Dipakai oleh layar "Catat Belanja/Pemasukan" (Sprint 5/9).
 */
const DB_NAME = 'keuangan-offline';
const STORE = 'antrian';

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'client_ref' });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export function uuid() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (crypto.getRandomValues(new Uint8Array(1))[0] % 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

/** Simpan transaksi ke antrian (status: pending). */
export async function enqueue(endpoint, payload) {
    const db = await openDb();
    const item = { client_ref: payload.client_ref || uuid(), endpoint, payload, status: 'pending', ts: Date.now() };
    item.payload.client_ref = item.client_ref;
    await tx(db, 'readwrite', (s) => s.put(item));
    return item;
}

/** Kirim seluruh antrian pending. Idempoten: server dedupe by client_ref. */
export async function flush(csrfToken) {
    const db = await openDb();
    const items = await tx(db, 'readonly', (s) => reqAll(s));
    for (const item of items.filter((i) => i.status === 'pending')) {
        try {
            const res = await fetch(item.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken || '' },
                credentials: 'same-origin',
                body: JSON.stringify(item.payload),
            });
            if (res.ok) {
                item.status = 'synced';
                await tx(db, 'readwrite', (s) => s.put(item));
            }
        } catch (e) {
            // tetap pending → retry saat online berikutnya
        }
    }
}

export async function pendingCount() {
    const db = await openDb();
    const items = await tx(db, 'readonly', (s) => reqAll(s));
    return items.filter((i) => i.status === 'pending').length;
}

function tx(db, mode, fn) {
    return new Promise((resolve, reject) => {
        const t = db.transaction(STORE, mode);
        const s = t.objectStore(STORE);
        const r = fn(s);
        t.oncomplete = () => resolve(r && r.result);
        t.onerror = () => reject(t.error);
    });
}

function reqAll(store) {
    const r = store.getAll();
    return r;
}

// Auto-flush saat kembali online.
if (typeof window !== 'undefined') {
    window.addEventListener('online', () => flush(document.querySelector('meta[name=csrf-token]')?.content));
}
