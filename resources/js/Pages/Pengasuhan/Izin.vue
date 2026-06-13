<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, reactive } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, IconCircle, StatusBadge, Chip, UiButton, BottomSheet, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: '' },
    daftar: { type: Array, default: () => [] },
})

// State lokal status (optimistic) — kunci id -> status.
const statusLokal = reactive(
    Object.fromEntries(props.daftar.map((d) => [d.id, d.status])),
)

const sheet = ref(false)
const aktif = ref(null)
const aksi = ref('setuju') // 'setuju' | 'tolak'
const toast = ref('')

function buka(item, mode) {
    aktif.value = item
    aksi.value = mode
    sheet.value = true
}

// Optimistic — ubah status lokal, tutup sheet, tampilkan toast. Tidak POST.
function konfirmasi() {
    if (!aktif.value) return
    statusLokal[aktif.value.id] = aksi.value === 'setuju' ? 'terverifikasi' : 'ditolak'
    toast.value = `Izin ${aktif.value.santri} ${aksi.value === 'setuju' ? 'disetujui' : 'ditolak'}.`
    sheet.value = false
    setTimeout(() => (toast.value = ''), 2800)
}
</script>

<template>
    <Head title="Izin Santri" />

    <AppShell :nav-items="navigation" brand-name="Kepengasuhan">
        <div class="space-y-5">
            <PageHeader kicker="Perizinan" title="Izin Keluar & Pulang" subtitle="Tinjau pengajuan santri binaan" />

            <p v-if="toast" class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-100">
                {{ toast }}
            </p>

            <SectionCard kicker="Pengajuan" title="Daftar Izin" flush>
                <EmptyState
                    v-if="daftar.length === 0"
                    icon="document" tone="emerald"
                    title="Belum ada pengajuan"
                    message="Tidak ada permohonan izin yang perlu ditinjau saat ini."
                />
                <div v-else class="divide-y divide-slate-100">
                    <ListRow v-for="d in daftar" :key="d.id">
                        <template #leading>
                            <IconCircle :icon="d.jenis === 'Pulang' ? 'arrow-up-right' : 'document'" :tone="d.jenis === 'Pulang' ? 'amber' : 'blue'" />
                        </template>
                        <template #title>{{ d.santri }}</template>
                        <template #subtitle>{{ d.keperluan }}</template>
                        <template #trailing>
                            <StatusBadge :status="statusLokal[d.id]" size="sm" />
                        </template>
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-400">
                                <Chip :active="false" tone="soft" size="sm">{{ d.jenis }}</Chip>
                                <span>{{ d.kelas }}</span>
                                <span>· {{ d.mulai }} → {{ d.selesai }}</span>
                            </div>
                            <div v-if="statusLokal[d.id] === 'pending'" class="flex gap-2">
                                <UiButton type="button" variant="primary" size="sm" icon-left="check" @click="buka(d, 'setuju')">Setujui</UiButton>
                                <UiButton type="button" variant="danger" size="sm" icon-left="x-circle" @click="buka(d, 'tolak')">Tolak</UiButton>
                            </div>
                        </div>
                    </ListRow>
                </div>
            </SectionCard>
        </div>

        <!-- Konfirmasi Setujui / Tolak (optimistic, tanpa POST) -->
        <BottomSheet v-model="sheet" :title="aksi === 'setuju' ? 'Setujui Izin' : 'Tolak Izin'">
            <div v-if="aktif" class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Santri</span>
                    <span class="font-semibold text-slate-900">{{ aktif.santri }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Jenis</span>
                    <span class="font-semibold text-slate-900">{{ aktif.jenis }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Keperluan</span>
                    <span class="text-right font-semibold text-slate-900">{{ aktif.keperluan }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Periode</span>
                    <span class="text-right font-semibold text-slate-900">{{ aktif.mulai }} → {{ aktif.selesai }}</span>
                </div>
            </div>
            <template #footer>
                <div class="flex gap-3">
                    <UiButton type="button" variant="secondary" block @click="sheet = false">Batal</UiButton>
                    <UiButton
                        type="button"
                        :variant="aksi === 'setuju' ? 'primary' : 'danger'"
                        block
                        :icon-left="aksi === 'setuju' ? 'check' : 'x-circle'"
                        @click="konfirmasi"
                    >
                        {{ aksi === 'setuju' ? 'Setujui' : 'Tolak' }}
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
