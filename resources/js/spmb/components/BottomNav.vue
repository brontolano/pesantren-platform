<script setup>
// Material bottom navigation — fixed, edge-to-edge, aman dari gesture bar
// (safe-area-inset-bottom). Tab aktif ditentukan dari URL berjalan.
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const current = computed(() => page.url || '/spmb');

const tabs = [
    { label: 'Beranda', href: '/spmb', match: ['/spmb'], icon: 'home' },
    { label: 'Berkas', href: '/spmb/berkas', match: ['/spmb/berkas'], icon: 'doc' },
    { label: 'Tagihan', href: '/spmb/tagihan', match: ['/spmb/tagihan'], icon: 'receipt' },
    { label: 'Ujian', href: '/spmb/ujian', match: ['/spmb/ujian'], icon: 'edit' },
    { label: 'Info', href: '/spmb/pengumuman', match: ['/spmb/pengumuman'], icon: 'bell' },
];

const isActive = (tab) => {
    const path = current.value.split('?')[0].replace(/\/$/, '') || '/spmb';
    if (tab.href === '/spmb') {
        return path === '/spmb';
    }
    return path === tab.href || path.startsWith(tab.href + '/');
};
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-30 border-t border-black/5 bg-white/95 backdrop-blur"
        style="padding-bottom: env(safe-area-inset-bottom)"
    >
        <div class="mx-auto flex h-14 w-full max-w-screen-sm items-stretch">
            <Link
                v-for="tab in tabs"
                :key="tab.href"
                :href="tab.href"
                class="group relative flex flex-1 flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition"
                :class="isActive(tab) ? 'text-[var(--spmb-primary)]' : 'text-stone-500 active:text-stone-800'"
            >
                <span
                    class="flex h-7 w-12 items-center justify-center rounded-full transition"
                    :class="isActive(tab) ? 'bg-[var(--spmb-primary)]/12' : ''"
                >
                    <!-- Ikon -->
                    <svg v-if="tab.icon === 'home'" class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5" /><path d="M5 9.5V21h14V9.5" /></svg>
                    <svg v-else-if="tab.icon === 'doc'" class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v5h5" /><path d="M14 3H6v18h12V8z" /><path d="M9 13h6M9 17h6" /></svg>
                    <svg v-else-if="tab.icon === 'receipt'" class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3v18l2-1 2 1 2-1 2 1 2-1 2 1V3l-2 1-2-1-2 1-2-1-2 1z" /><path d="M9 8h6M9 12h6" /></svg>
                    <svg v-else-if="tab.icon === 'edit'" class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
                    <svg v-else class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.5 21a1.7 1.7 0 0 1-3 0" /></svg>
                </span>
                <span>{{ tab.label }}</span>
            </Link>
        </div>
    </nav>
</template>
