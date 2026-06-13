<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, MoneyText, IconCircle, BottomSheet, UiButton } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
    slip: { type: Array, default: () => [] },
})

// final -> 'lunas' (emerald), draft -> 'menunggu' (amber); label di-override.
const statusKey = (s) => (s === 'final' ? 'lunas' : 'menunggu')
const statusLabel = (s) => (s === 'final' ? 'Final' : 'Draft')

const sheetOpen = ref(false)
const selected = ref(null)

function openDetail(s) {
    selected.value = s
    sheetOpen.value = true
}

const totalNeto = computed(() => props.slip.reduce((sum, s) => sum + (s.neto ?? 0), 0))
</script>

<template>
    <Head title="Slip Gaji" />

    <AppShell :nav-items="navigation" brand-name="HRD Kepegawaian">
        <div class="space-y-5">
            <PageHeader kicker="Penggajian" title="Slip Gaji" :subtitle="`Periode ${periode}`" />

            <SectionCard kicker="Ringkasan" title="Total Neto Periode Ini">
                <MoneyText :amount="totalNeto" size="xl" tone="emerald" />
                <p class="mt-1 text-xs font-medium text-slate-400">{{ slip.length }} slip pegawai</p>
            </SectionCard>

            <SectionCard flush>
                <div class="divide-y divide-slate-100">
                    <button
                        v-for="s in slip"
                        :key="s.id"
                        type="button"
                        class="block w-full text-left"
                        @click="openDetail(s)"
                    >
                        <ListRow :title="s.nama" :subtitle="s.jabatan" chevron>
                            <template #leading><IconCircle icon="receipt" tone="blue" /></template>
                            <template #trailing>
                                <MoneyText :amount="s.neto" tone="emerald" size="md" />
                                <StatusBadge :status="statusKey(s.status)" :label="statusLabel(s.status)" size="sm" class="mt-1" />
                            </template>
                        </ListRow>
                    </button>
                </div>
            </SectionCard>
        </div>

        <BottomSheet v-model="sheetOpen" title="Rincian Slip Gaji">
            <div v-if="selected" class="space-y-4">
                <div>
                    <p class="text-base font-bold text-slate-900">{{ selected.nama }}</p>
                    <p class="text-sm text-slate-500">{{ selected.jabatan }} · {{ periode }}</p>
                    <StatusBadge :status="statusKey(selected.status)" :label="statusLabel(selected.status)" size="sm" class="mt-2" />
                </div>

                <div class="space-y-3 rounded-2xl bg-slate-50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Gaji Bruto</span>
                        <MoneyText :amount="selected.bruto" size="md" />
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Tunjangan</span>
                        <MoneyText :amount="selected.tunjangan" size="md" tone="emerald" />
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Potongan</span>
                        <MoneyText :amount="-selected.potongan" size="md" tone="rose" signed />
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-200 pt-3">
                        <span class="text-sm font-bold text-slate-900">Gaji Neto</span>
                        <MoneyText :amount="selected.neto" size="lg" tone="emerald" />
                    </div>
                </div>
            </div>

            <template #footer>
                <UiButton variant="secondary" block @click="sheetOpen = false">Tutup</UiButton>
            </template>
        </BottomSheet>
    </AppShell>
</template>
