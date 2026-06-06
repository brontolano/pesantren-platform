<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import UiIcon from './UiIcon.vue'

/**
 * ListRow — baris daftar serbaguna (tagihan, mutasi, pembayaran).
 * Bisa jadi <Link> (jika href diisi) atau <div>.
 * Slot:
 *   #leading  — ikon/avatar kiri (opsional)
 *   #title    — judul (atau pakai prop `title`)
 *   #subtitle — sub-teks (atau prop `subtitle`)
 *   #trailing — konten kanan (nominal, badge)
 *   default   — konten tambahan di bawah (mis. grid rincian)
 * - chevron: tampilkan panah kanan (untuk baris yang bisa diklik)
 */
const props = defineProps({
    href: { type: String, default: null },
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    chevron: { type: Boolean, default: false },
})

const tag = computed(() => (props.href ? Link : 'div'))
const interactive = computed(() => !!props.href)
</script>

<template>
    <component
        :is="tag"
        :href="href || undefined"
        class="flex w-full items-start gap-3 px-4 py-4 text-left transition-all duration-200"
        :class="interactive ? 'hover:bg-slate-50/70 active:bg-slate-100/70 focus-visible:outline-none focus-visible:bg-slate-50' : ''"
    >
        <div v-if="$slots.leading" class="shrink-0 pt-0.5">
            <slot name="leading" />
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-[15px] font-bold tracking-tight text-slate-900">
                        <slot name="title">{{ title }}</slot>
                    </p>
                    <p v-if="$slots.subtitle || subtitle" class="mt-0.5 truncate text-sm text-slate-500">
                        <slot name="subtitle">{{ subtitle }}</slot>
                    </p>
                </div>
                <div v-if="$slots.trailing" class="shrink-0 text-right">
                    <slot name="trailing" />
                </div>
            </div>
            <div v-if="$slots.default" class="mt-2">
                <slot />
            </div>
        </div>

        <UiIcon
            v-if="chevron"
            name="chevron-right"
            class="mt-1 h-4 w-4 shrink-0 text-slate-300"
        />
    </component>
</template>
