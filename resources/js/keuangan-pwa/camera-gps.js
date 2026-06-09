/**
 * Helper Kamera + GPS untuk bukti belanja/jasa (KEU-A04).
 * Kamera langsung (capture) + geotag + timestamp, lalu kompres sebelum upload
 * ke /_tenant-storage (KEU-A05). Dipakai layar lapangan (Sprint 5/9).
 */

/** Ambil koordinat GPS sekarang. Reject bila ditolak/mati (anti-manipulasi). */
export function ambilLokasi(opts = { enableHighAccuracy: true, timeout: 10000 }) {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) return reject(new Error('GPS tidak didukung perangkat.'));
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
                accuracy: pos.coords.accuracy,
                diambil_pada: new Date().toISOString(),
            }),
            (err) => reject(new Error('Gagal ambil GPS: ' + err.message)),
            opts
        );
    });
}

/** Kompres File gambar ke JPEG (maks dimensi & kualitas) untuk hemat kuota. */
export function kompresGambar(file, maxDim = 1280, quality = 0.7) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const url = URL.createObjectURL(file);
        img.onload = () => {
            const scale = Math.min(1, maxDim / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Kompres gagal'))), 'image/jpeg', quality);
        };
        img.onerror = () => reject(new Error('Gambar tidak valid'));
        img.src = url;
    });
}

/** Gabungkan foto + GPS jadi FormData untuk diunggah. */
export async function siapkanBukti(file) {
    const [lokasi, blob] = await Promise.all([ambilLokasi(), kompresGambar(file)]);
    const fd = new FormData();
    fd.append('foto', blob, 'bukti.jpg');
    fd.append('gps_latitude', lokasi.lat);
    fd.append('gps_longitude', lokasi.lng);
    fd.append('diambil_pada', lokasi.diambil_pada);
    return fd;
}
