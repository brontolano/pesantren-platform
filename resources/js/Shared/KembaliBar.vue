<script setup>
/**
 * KembaliBar — tombol "Kembali" standar halaman detail staf (owner 2026-07-06:
 * bottom-nav tetap 4 tab mockup; navigasi kontekstual lewat tombol kembali).
 * history.back() bila ada riwayat; fallback ke tab Menu shell.
 */
function kembali() {
    if (window.history.length > 1) {
        window.history.back()
    } else {
        window.location.href = '/staf?tab=menu'
    }
}
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-full bg-white px-3.5 py-2 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-[var(--wali-border,#efe7da)] transition active:scale-95"
        aria-label="Kembali"
        @click="kembali"
    >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
        Kembali
    </button>
</template>
