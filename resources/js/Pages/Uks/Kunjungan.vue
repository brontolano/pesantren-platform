<script setup>
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, IconCircle, UiButton, BottomSheet, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    kunjungan: { type: Array, default: () => [] },
})

const statusLabel = { pulang: 'Pulang', istirahat: 'Istirahat', rujuk: 'Dirujuk' }

// Optimistic-only: tandai kunjungan yang sudah "ditutup" di sisi klien (tanpa POST).
const ditutup = ref(new Set())
const sheetOpen = ref(false)
const aktif = ref(null)
const toast = ref('')

function konfirmasiTutup(k) {
    aktif.value = k
    sheetOpen.value = true
}

function tutup() {
    if (aktif.value) {
        ditutup.value = new Set([...ditutup.value, aktif.value.id])
        toast.value = `Kunjungan ${aktif.value.nama} ditutup.`
        setTimeout(() => (toast.value = ''), 2500)
    }
    sheetOpen.value = false
}
</script>

<template>
    <Head title="Kunjungan UKS" />

    <AppShell :nav-items="navigation" brand-name="Kesehatan UKS">
        <div class="space-y-5">
            <PageHeader kicker="Hari ini" title="Kunjungan" subtitle="Santri yang berkunjung ke UKS hari ini" />

            <UiButton variant="primary" block icon-left="plus" href="/uks/kunjungan/catat">
                Catat Kunjungan Baru
            </UiButton>

            <SectionCard flush>
                <EmptyState
                    v-if="!kunjungan.length"
                    icon="check-circle"
                    title="Belum ada kunjungan"
                    message="Kunjungan santri hari ini akan tampil di sini."
                />
                <div v-else class="divide-y divide-slate-100">
                    <ListRow
                        v-for="k in kunjungan"
                        :key="k.id"
                        :title="k.nama"
                        :subtitle="`${k.kelas} • ${k.keluhan}`"
                    >
                        <template #leading>
                            <IconCircle icon="heart" tone="rose" size="md" />
                        </template>
                        <template #trailing>
                            <span class="text-xs font-medium text-slate-400">{{ k.jam }}</span>
                        </template>

                        <div class="flex items-center justify-between gap-3">
                            <StatusBadge
                                :status="ditutup.has(k.id) ? 'lunas' : k.status"
                                :label="ditutup.has(k.id) ? 'Selesai' : statusLabel[k.status]"
                                size="sm"
                            />
                            <UiButton
                                v-if="!ditutup.has(k.id)"
                                variant="ghost"
                                size="sm"
                                icon-left="check"
                                @click="konfirmasiTutup(k)"
                            >
                                Tutup
                            </UiButton>
                            <span v-else class="text-xs font-semibold text-emerald-600">Ditutup</span>
                        </div>
                    </ListRow>
                </div>
            </SectionCard>

            <p
                v-if="toast"
                class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-100"
            >
                {{ toast }}
            </p>
        </div>

        <BottomSheet v-model="sheetOpen" title="Tutup kunjungan?">
            <p class="text-sm leading-6 text-slate-600">
                Tandai kunjungan <span class="font-bold text-slate-900">{{ aktif?.nama }}</span>
                ({{ aktif?.kelas }}) sebagai selesai. Tindakan: {{ aktif?.tindakan }}.
            </p>
            <template #footer>
                <div class="flex gap-3">
                    <UiButton variant="secondary" block @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" block icon-left="check" @click="tutup">Ya, Tutup</UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
