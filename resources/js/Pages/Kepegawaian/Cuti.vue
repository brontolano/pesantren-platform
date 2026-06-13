<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, reactive } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, IconCircle, BottomSheet, UiButton, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    pengajuan: { type: Array, default: () => [] },
})

// Salinan reaktif lokal — aksi setujui/tolak optimistic (tidak POST ke server).
const items = reactive(props.pengajuan.map((p) => ({ ...p })))

const JENIS_META = {
    'Cuti Tahunan': { icon: 'calendar', tone: 'blue' },
    'Cuti Sakit': { icon: 'heart', tone: 'rose' },
    'Cuti Melahirkan': { icon: 'heart', tone: 'amber' },
}
const metaFor = (j) => JENIS_META[j] ?? { icon: 'calendar', tone: 'slate' }

const sheetOpen = ref(false)
const selected = ref(null)
const aksi = ref('setujui')
const toast = ref('')

function buka(item, mode) {
    selected.value = item
    aksi.value = mode
    sheetOpen.value = true
}

function konfirmasi() {
    if (selected.value) {
        const target = items.find((i) => i.id === selected.value.id)
        if (target) target.status = aksi.value === 'setujui' ? 'terverifikasi' : 'ditolak'
        toast.value = aksi.value === 'setujui' ? 'Pengajuan cuti disetujui.' : 'Pengajuan cuti ditolak.'
        setTimeout(() => (toast.value = ''), 2600)
    }
    sheetOpen.value = false
}
</script>

<template>
    <Head title="Pengajuan Cuti" />

    <AppShell :nav-items="navigation" brand-name="HRD Kepegawaian">
        <div class="space-y-5">
            <PageHeader kicker="Kepegawaian" title="Pengajuan Cuti" subtitle="Tinjau & putuskan pengajuan pegawai" />

            <div
                v-if="toast"
                class="flex items-center gap-2 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100"
                role="status"
            >
                <IconCircle icon="check-circle" tone="emerald" size="sm" />
                {{ toast }}
            </div>

            <SectionCard v-if="items.length" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="c in items" :key="c.id" :title="c.nama" :subtitle="`${c.jenis} · ${c.tanggal}`">
                        <template #leading><IconCircle :icon="metaFor(c.jenis).icon" :tone="metaFor(c.jenis).tone" /></template>
                        <template #trailing>
                            <StatusBadge :status="c.status" size="sm" />
                        </template>
                        <div v-if="c.status === 'menunggu'" class="flex gap-2">
                            <UiButton variant="primary" size="sm" icon-left="check" @click="buka(c, 'setujui')">Setujui</UiButton>
                            <UiButton variant="danger" size="sm" icon-left="x-circle" @click="buka(c, 'tolak')">Tolak</UiButton>
                        </div>
                    </ListRow>
                </div>
            </SectionCard>

            <EmptyState
                v-else
                icon="calendar"
                title="Tidak ada pengajuan"
                message="Belum ada pengajuan cuti yang perlu ditinjau."
            />
        </div>

        <BottomSheet v-model="sheetOpen" :title="aksi === 'setujui' ? 'Setujui Pengajuan Cuti' : 'Tolak Pengajuan Cuti'">
            <div v-if="selected" class="space-y-3">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-base font-bold text-slate-900">{{ selected.nama }}</p>
                    <p class="text-sm text-slate-500">{{ selected.jabatan }}</p>
                    <div class="mt-3 space-y-1.5 text-sm">
                        <p><span class="text-slate-400">Jenis:</span> <span class="font-semibold text-slate-800">{{ selected.jenis }}</span></p>
                        <p><span class="text-slate-400">Tanggal:</span> <span class="font-semibold text-slate-800">{{ selected.tanggal }}</span></p>
                        <p><span class="text-slate-400">Durasi:</span> <span class="font-semibold text-slate-800">{{ selected.durasi }} hari</span></p>
                        <p><span class="text-slate-400">Alasan:</span> <span class="text-slate-700">{{ selected.alasan }}</span></p>
                    </div>
                </div>
                <p class="px-1 text-xs leading-5 text-slate-400">
                    {{ aksi === 'setujui' ? 'Pegawai akan diberi tahu bahwa cuti disetujui.' : 'Pegawai akan diberi tahu bahwa cuti ditolak.' }}
                </p>
            </div>

            <template #footer>
                <div class="flex gap-3">
                    <UiButton variant="secondary" block @click="sheetOpen = false">Batal</UiButton>
                    <UiButton :variant="aksi === 'setujui' ? 'primary' : 'danger'" block @click="konfirmasi">
                        {{ aksi === 'setujui' ? 'Setujui' : 'Tolak' }}
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
