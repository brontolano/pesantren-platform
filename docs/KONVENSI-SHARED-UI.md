# Konvensi UI Bersama — `@/Shared` vs `@/Components/Ui`

Fondasi F0 rencana "PWA All-in-One". Singkat dan mengikat.

## Kapan pakai apa

| Situasi | Pakai |
|---|---|
| Komponen baru untuk app lintas-peran (guru/kasir/pimpinan/santri/keuangan) | `@/Shared` (`import { AppShell, HeroHeader, BottomNav, UiCard } from '@/Shared'`) |
| Halaman Portal Wali existing | Tetap `@/Components/Ui` — **zero-churn**, jangan migrasi massal |
| Komponen primitif baru yang netral-peran (button/card/badge) | Tambahkan di `@/Components/Ui`, lalu re-export otomatis via `@/Shared` (`export * from '@/Components/Ui'`) |
| Komponen shell/komposit baru (layout, nav, header) | Langsung di `resources/js/Shared/` + daftarkan di `Shared/index.js` |

## Token warna

- Komponen `@/Shared` baru memakai token netral `--app-*` (alias 1:1 ke `--wali-*` → nilai identik, tanpa perubahan visual).
- **JANGAN** mengubah nilai `--wali-*` — itu kontrak visual Portal Wali.
- Token kanonik design-system (`--brand-*`, `--surface-*`, `--text-*`, `--pst-*`) tetap sumber kebenaran; `--app-*`/`--wali-*` hanyalah alias.

## Aturan redesign "template-only"

Saat meredesain tampilan sebuah portal:

1. Ubah **hanya** blok `<template>` (+ CSS terkait) — logika `<script setup>`, props, emits, dan alur data **tidak disentuh**.
2. Kontrak props/slots komponen Shared bersifat **additif**: boleh menambah prop/slot opsional ber-default aman (mis. slot `#actions` HeroHeader, prop `badge` item BottomNav); dilarang mengubah/menghapus yang sudah dipakai.
3. Portal Wali adalah referensi visual kanonik — perubahan Shared tidak boleh mengubah tampilan Portal Wali sedikit pun.

## Hygiene perangkat bersama

Kunci localStorage sensitif per-user **wajib** didaftarkan di
`resources/js/Shared/authStorage.js` (`SENSITIVE_STORAGE_KEYS`). Pembersihan
otomatis berjalan dari `app.js` setiap halaman ter-render tanpa user login.
