<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, SegmentedControl, SectionCard, ListRow, StatusBadge, IconCircle, EmptyState, BottomSheet, UiButton } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: 'Santri Pengabdian' },
    mendatang: { type: Array, default: () => [] },
    selesai: { type: Array, default: () => [] },
})

const tab = ref('mendatang')
const tabOptions = [
    { value: 'mendatang', label: 'Mendatang', icon: 'clock' },
    { value: 'selesai', label: 'Selesai', icon: 'check-circle' },
]

const daftar = computed(() => (tab.value === 'mendatang' ? props.mendatang : props.selesai))

const sheetOpen = ref(false)
const selected = ref(null)

function buka(item) {
    selected.value = item
    sheetOpen.value = true
}
</script>

<template>
    <Head title="Jadwal Khidmah" />

    <AppShell :nav-items="navigation" brand-name="Pengabdian Santri">
        <div class="space-y-5">
            <PageHeader kicker="Penugasan" title="Jadwal Khidmah" subtitle="Kebersihan masjid, pengajaran TPQ, piket dapur" />

            <SegmentedControl v-model="tab" :options="tabOptions" />

            <SectionCard flush>
                <div v-if="daftar.length" class="divide-y divide-slate-100">
                    <ListRow
                        v-for="item in daftar"
                        :key="item.id"
                        :title="item.judul"
                        :subtitle="`${item.waktu} · ${item.lokasi}`"
                        chevron
                        @click="buka(item)"
                        class="cursor-pointer"
                    >
                        <template #leading>
                            <IconCircle :icon="tab === 'selesai' ? 'check-circle' : 'calendar'" :tone="tab === 'selesai' ? 'slate' : 'emerald'" size="md" />
                        </template>
                        <template #trailing>
                            <StatusBadge :status="tab === 'selesai' ? 'terverifikasi' : 'info'" size="sm" :label="tab === 'selesai' ? 'Selesai' : item.durasi" />
                        </template>
                    </ListRow>
                </div>
                <EmptyState
                    v-else
                    icon="calendar"
                    title="Belum ada jadwal"
                    :message="tab === 'mendatang' ? 'Tidak ada khidmah mendatang saat ini.' : 'Belum ada khidmah yang selesai.'"
                />
            </SectionCard>
        </div>

        <BottomSheet v-model="sheetOpen" :title="selected?.judul">
            <div v-if="selected" class="space-y-4">
                <div class="flex items-center gap-3">
                    <IconCircle icon="calendar" tone="emerald" size="lg" />
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ selected.waktu }}</p>
                        <p class="text-sm text-slate-500">{{ selected.lokasi }}</p>
                    </div>
                </div>

                <dl class="space-y-2.5 rounded-2xl bg-slate-50 px-4 py-3.5 ring-1 ring-inset ring-slate-100">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-sm text-slate-500">Pembimbing</dt>
                        <dd class="text-sm font-semibold text-slate-800">{{ selected.pembimbing }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-sm text-slate-500">Durasi</dt>
                        <dd class="text-sm font-semibold text-slate-800">{{ selected.durasi }}</dd>
                    </div>
                </dl>

                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Catatan</p>
                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ selected.catatan }}</p>
                </div>
            </div>

            <template #footer>
                <UiButton variant="primary" block icon-left="check" @click="sheetOpen = false">Mengerti</UiButton>
            </template>
        </BottomSheet>
    </AppShell>
</template>
