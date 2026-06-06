# TWA (Trusted Web Activity) → Google Play — PWA SPMB

Membungkus PWA `/spmb` jadi APK/AAB Android via **Bubblewrap** lalu publish ke Play Store.
Langkah PUBLISH & SIGNING bersifat **MANUAL** (butuh akun Google Play + keystore).

## Prasyarat
- PWA `/spmb` sudah installable (manifest `/spmb/manifest.json` + service worker `/spmb/sw.js` ✅).
- Domain HTTPS: `https://pesantren.asy-syifaa.com` (per tenant).
- Node + `@bubblewrap/cli` (`npm i -g @bubblewrap/cli`).

## Langkah
1. `bubblewrap init --manifest https://pesantren.asy-syifaa.com/spmb/manifest.json`
   - Host: `pesantren.asy-syifaa.com` · Start URL: `/spmb` · Scope: `/spmb/`.
   - appId mis. `com.asysyifaa.spmb`.
2. `bubblewrap build` → hasil `app-release-signed.apk` / `.aab` + keystore.
3. Ambil **SHA-256 fingerprint** signing key:
   `keytool -list -v -keystore <keystore> -alias <alias>` → salin `SHA256`.
   (Bila pakai Play App Signing, ambil fingerprint dari Play Console → Setup → App integrity.)
4. Isi `public/.well-known/assetlinks.json` (lihat template di bawah) dengan fingerprint itu, deploy.
   Verifikasi: buka `https://pesantren.asy-syifaa.com/.well-known/assetlinks.json`.
5. Upload `.aab` ke Play Console → isi listing → rilis.

## Template assetlinks.json (isi fingerprint lalu taruh di public/.well-known/assetlinks.json)
```json
[
  {
    "relation": ["delegate_permission/common.handle_all_urls"],
    "target": {
      "namespace": "android_app",
      "package_name": "com.asysyifaa.spmb",
      "sha256_cert_fingerprints": ["AA:BB:CC:... (SHA256 dari signing key)"]
    }
  }
]
```

## Catatan multi-tenant
File `public/.well-known/assetlinks.json` dilayani untuk SEMUA domain (public dir bersama).
Untuk beberapa TWA per tenant, tiap entri app bisa ditambahkan ke array yang sama
(atau layani per-domain lewat Caddy bila diperlukan). Saat ini berisi `[]` (belum ada app terverifikasi).
