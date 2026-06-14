<script setup>
// Kartu section yang bisa dilipat (accordion) — mengelompokkan field biodata
// per bagian (Data Pribadi, Alamat, dst). Header menampilkan judul + badge
// progres opsional; isi muncul/sembunyi dengan transisi halus.
import { ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    badge: { type: String, default: '' },
    icon: { type: String, default: '' },
    defaultOpen: { type: Boolean, default: true },
});

const open = ref(props.defaultOpen);
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-stone-200/80 bg-white shadow-sm">
        <button
            type="button"
            class="flex w-full items-center gap-3 px-4 py-3.5 text-left transition active:bg-stone-50"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span
                v-if="icon"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[var(--spmb-primary)]/12 text-base"
                aria-hidden="true"
            >{{ icon }}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-[15px] font-semibold text-stone-900">{{ title }}</span>
                <span v-if="subtitle" class="block truncate text-[12px] text-stone-500">{{ subtitle }}</span>
            </span>
            <span
                v-if="badge"
                class="shrink-0 rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600"
            >{{ badge }}</span>
            <svg
                class="h-5 w-5 shrink-0 text-stone-400 transition-transform"
                :class="open ? 'rotate-180' : ''"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
            >
                <path d="M6 9l6 6 6-6" />
            </svg>
        </button>

        <Transition name="spmb-collapse">
            <div v-show="open" class="border-t border-stone-100 px-4 pb-4 pt-4">
                <slot />
            </div>
        </Transition>
    </section>
</template>

<style scoped>
.spmb-collapse-enter-active,
.spmb-collapse-leave-active {
    transition: opacity 0.18s ease;
}
.spmb-collapse-enter-from,
.spmb-collapse-leave-to {
    opacity: 0;
}
</style>
