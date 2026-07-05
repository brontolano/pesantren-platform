<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, MoneyText, IconCircle, BottomSheet, UiButton, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    donasi: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
})

const STATUS_META = {
    menunggu_transfer: { key: 'menunggu', label: 'Menunggu' },
    dikonfirmasi: { key: 'lunas', label: 'Dikonfirmasi' },
    ditolak: { key: 'ditolak', label: 'Ditolak' },
}

const sheetOpen = ref(false)
const selected = ref(null)
const memproses = ref(false)

function bukaDetail(d) {
    selected.value = d
    sheetOpen.value = true
}

function konfirmasi() {
    if (!selected.value) return
    memproses.value = true
    router.post(`/baitul-maal/donasi/${selected.value.id}/konfirmasi`, {}, {
        preserveScroll: true,
        onFinish: () => { memproses.value = false; sheetOpen.value = false },
    })
}

function tolak() {
    if (!selected.value) return
    memproses.value = true
    router.post(`/baitul-maal/donasi/${selected.value.id}/tolak`, {}, {
        preserveScroll: true,
        onFinish: () => { memproses.value = false; sheetOpen.value = false },
    })
}
</script>

<template>
    <Head title="Donasi · Baitul Maal" />

    <AppShell :nav-items="navigation" brand-name="Baitul Maal">
        <div class="space-y-5">
            <PageHeader kicker="Baitul Maal" title="Donasi Masuk" :subtitle="`${meta.total ?? donasi.length} entri`" />

            <SectionCard flush>
                <EmptyState
                    v-if="!donasi.length"
                    icon="heart"
                    title="Belum ada donasi"
                    message="Donasi dari donatur publik akan tampil di sini untuk dikonfirmasi."
                />
                <div v-else class="divide-y divide-slate-100">
                    <button
                        v-for="d in donasi"
                        :key="d.id"
                        type="button"
                        class="block w-full text-left"
                        @click="bukaDetail(d)"
                    >
                        <ListRow :title="d.nama_donatur" :subtitle="`${d.program} · ${d.tanggal}`" chevron>
                            <template #leading><IconCircle icon="heart" tone="rose" /></template>
                            <template #trailing>
                                <MoneyText :amount="d.nominal" size="md" tone="emerald" />
                                <StatusBadge
                                    :status="STATUS_META[d.status]?.key ?? 'info'"
                                    :label="STATUS_META[d.status]?.label ?? d.status"
                                    size="sm"
                                    class="mt-1"
                                />
                            </template>
                        </ListRow>
                    </button>
                </div>
            </SectionCard>
        </div>

        <BottomSheet v-model="sheetOpen" title="Detail Donasi">
            <div v-if="selected" class="space-y-3">
                <div>
                    <p class="text-base font-bold text-slate-900">{{ selected.nama_donatur }}</p>
                    <p class="text-sm text-slate-500">{{ selected.kontak || '—' }} · {{ selected.program }}</p>
                </div>
                <MoneyText :amount="selected.nominal" size="lg" tone="emerald" />
                <a
                    v-if="selected.bukti_url"
                    :href="selected.bukti_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700 underline"
                >
                    Lihat bukti transfer
                </a>
            </div>
            <template #footer>
                <div v-if="can.kelola && selected?.status === 'menunggu_transfer'" class="flex gap-2">
                    <UiButton variant="danger" block :disabled="memproses" @click="tolak">Tolak</UiButton>
                    <UiButton variant="primary" icon-left="check" block :disabled="memproses" @click="konfirmasi">
                        Konfirmasi
                    </UiButton>
                </div>
                <UiButton v-else variant="secondary" block @click="sheetOpen = false">Tutup</UiButton>
            </template>
        </BottomSheet>
    </AppShell>
</template>
