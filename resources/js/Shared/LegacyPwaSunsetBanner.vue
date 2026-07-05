<script setup>
import { computed, ref, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * F5 (sunset instalasi lama) — banner "Pasang aplikasi baru" ditampilkan
 * HANYA saat: (1) tenant punya flag `pwa.legacy_sw_selfdestruct` ON (shared
 * via Inertia prop `pwaLegacySwSelfdestruct`, lihat HandleInertiaRequests),
 * DAN (2) halaman berjalan dalam display-mode standalone (ikon lama yang
 * sudah ter-install di layar HP — tak bisa dihapus programatik, banner
 * adalah jalur UX untuk mengarahkan user ke aplikasi baru di root "/").
 *
 * Dormant secara default: flag OFF di semua tenant → komponen ini tak
 * pernah render di mana pun sampai owner mengaktifkan flag pasca F1 stabil.
 */
const page = usePage()

const flagOn = computed(() => Boolean(page.props.pwaLegacySwSelfdestruct))

const isStandalone = ref(false)

onMounted(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return
    }

    isStandalone.value = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true
})

// F5: hanya muncul di scope PWA lama yang sedang di-sunset — /guru /wali
// /keuangan. Bukan di /spmb (dikecualikan total) atau area lain (mis.
// Pimpinan/KepalaKeuangan yang juga pakai AppShell tapi bukan target F5).
const LEGACY_PREFIXES = ['/guru', '/wali', '/keuangan']

const isLegacyScope = computed(() => {
    const path = page.url ? page.url.split('?')[0] : ''

    return LEGACY_PREFIXES.some((prefix) => path === prefix || path.startsWith(`${prefix}/`))
})

const visible = computed(() => flagOn.value && isStandalone.value && isLegacyScope.value)
</script>

<template>
    <div
        v-if="visible"
        class="mx-4 mb-3 flex items-center justify-between gap-3 rounded-2xl bg-violet-50 px-4 py-3 text-sm text-violet-900 ring-1 ring-inset ring-violet-200"
        role="status"
    >
        <span>Ada aplikasi baru yang lebih lengkap.</span>
        <a
            href="/"
            class="shrink-0 rounded-full bg-violet-600 px-3 py-1.5 font-semibold text-white"
        >Pasang aplikasi baru</a>
    </div>
</template>
