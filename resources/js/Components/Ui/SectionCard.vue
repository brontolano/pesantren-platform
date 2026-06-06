<script setup>
/**
 * SectionCard — gabungan PageHeading + UiCard untuk satu blok konten.
 * Pakai untuk section "Riwayat", "Daftar tagihan", dll.
 * - kicker, title, subtitle (diteruskan ke PageHeading)
 * - flush: jika true, konten tanpa padding (mis. daftar ListRow penuh)
 * Slot: default (isi), #right (aksi kanan heading), #header (override heading)
 */
import PageHeading from './PageHeading.vue'
import UiCard from './UiCard.vue'

defineProps({
    kicker: { type: String, default: null },
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    flush: { type: Boolean, default: false },
})
</script>

<template>
    <section class="space-y-3">
        <slot name="header">
            <PageHeading
                v-if="title"
                :kicker="kicker"
                :title="title"
                :subtitle="subtitle"
            >
                <template v-if="$slots.right" #right>
                    <slot name="right" />
                </template>
            </PageHeading>
        </slot>

        <UiCard :padding="flush ? 'none' : 'md'">
            <slot />
        </UiCard>
    </section>
</template>
