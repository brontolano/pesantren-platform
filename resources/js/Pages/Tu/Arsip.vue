<script setup>
import { computed, ref } from 'vue'
import {
    AppShell, PageHeader, SectionCard, ListRow, IconCircle, Chip, EmptyState,
} from '@/Shared'

/**
 * Arsip — daftar dokumen dengan filter Chip kategori (surat/sk/sertifikat).
 * Filter client-side atas data dummy controller.
 */
const props = defineProps({
    userName: { type: String, default: 'Staf TU' },
    kategoriOptions: { type: Array, default: () => [] },
    dokumen: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const navItems = computed(() => props.navigation ?? [])

const kategori = ref('semua')
const hasil = computed(() =>
    props.dokumen.filter((d) => kategori.value === 'semua' || d.kategori === kategori.value),
)

const KATEGORI_META = {
    surat: { label: 'Surat', icon: 'document', tone: 'blue' },
    sk: { label: 'SK', icon: 'receipt', tone: 'amber' },
    sertifikat: { label: 'Sertifikat', icon: 'check-circle', tone: 'emerald' },
}
function meta(k) {
    return KATEGORI_META[k] ?? { label: k, icon: 'inbox', tone: 'slate' }
}
</script>

<template>
    <AppShell :nav-items="navItems" brand-name="Tata Usaha">
        <div class="space-y-5">
            <PageHeader kicker="Kearsipan" title="Arsip Dokumen" subtitle="Dokumen resmi pesantren" />

            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <Chip
                    v-for="k in kategoriOptions"
                    :key="k.value"
                    :active="kategori === k.value"
                    tone="soft"
                    size="sm"
                    @click="kategori = k.value"
                >
                    {{ k.label }}
                </Chip>
            </div>

            <SectionCard flush>
                <div v-if="hasil.length" class="divide-y divide-slate-100">
                    <ListRow
                        v-for="d in hasil"
                        :key="d.id"
                        :title="d.nama"
                        :subtitle="`${d.nomor} · ${d.tanggal}`"
                        chevron
                    >
                        <template #leading>
                            <IconCircle :icon="meta(d.kategori).icon" :tone="meta(d.kategori).tone" size="sm" />
                        </template>
                        <template #trailing>
                            <Chip :active="false" tone="soft" size="sm">{{ meta(d.kategori).label }}</Chip>
                        </template>
                    </ListRow>
                </div>
                <EmptyState
                    v-else
                    icon="inbox"
                    title="Arsip kosong"
                    message="Tidak ada dokumen pada kategori ini."
                />
            </SectionCard>
        </div>
    </AppShell>
</template>
