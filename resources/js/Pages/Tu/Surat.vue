<script setup>
import { computed, ref } from 'vue'
import {
    AppShell, PageHeader, SectionCard, ListRow, IconCircle, Chip,
    SegmentedControl, UiButton, EmptyState,
} from '@/Shared'

/**
 * Surat (buku agenda) — toggle Masuk/Keluar (SegmentedControl), daftar surat
 * dengan Chip jenis. Tombol catat surat keluar -> halaman CatatSurat.
 */
const props = defineProps({
    userName: { type: String, default: 'Staf TU' },
    arahOptions: { type: Array, default: () => [] },
    masuk: { type: Array, default: () => [] },
    keluar: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const navItems = computed(() => props.navigation ?? [])

const arah = ref('masuk')
const daftar = computed(() => (arah.value === 'masuk' ? props.masuk : props.keluar))
</script>

<template>
    <AppShell :nav-items="navItems" brand-name="Tata Usaha">
        <div class="space-y-5">
            <PageHeader kicker="Persuratan" title="Buku Agenda" subtitle="Catatan surat masuk & keluar" />

            <SegmentedControl v-model="arah" :options="arahOptions" />

            <SectionCard flush>
                <div v-if="daftar.length" class="divide-y divide-slate-100">
                    <ListRow
                        v-for="surat in daftar"
                        :key="surat.id"
                        :title="surat.perihal"
                        :subtitle="`${surat.dari} · ${surat.tanggal}`"
                    >
                        <template #leading>
                            <IconCircle
                                :icon="arah === 'masuk' ? 'arrow-down-left' : 'arrow-up-right'"
                                :tone="arah === 'masuk' ? 'blue' : 'amber'"
                                size="sm"
                            />
                        </template>
                        <template #default>
                            <div class="flex items-center gap-2">
                                <Chip :active="false" tone="soft" size="sm">{{ surat.jenis }}</Chip>
                                <span class="font-jost text-[11px] font-semibold tabular-nums text-slate-400">
                                    {{ surat.no }}
                                </span>
                            </div>
                        </template>
                    </ListRow>
                </div>
                <EmptyState
                    v-else
                    icon="document"
                    title="Belum ada surat"
                    :message="arah === 'masuk' ? 'Belum ada surat masuk tercatat.' : 'Belum ada surat keluar tercatat.'"
                />
            </SectionCard>

            <UiButton
                href="/tu/catat-surat"
                variant="primary"
                block
                icon-left="plus"
            >
                Catat Surat Keluar
            </UiButton>
        </div>
    </AppShell>
</template>
