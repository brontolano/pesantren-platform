<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, MoneyText, IconCircle, BottomSheet, UiButton, UiField, UiInput, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    jurnal: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
})

const statusLabel = { posted: 'Posted', reversed: 'Dibalikkan' }
const statusKey = (s) => (s === 'posted' ? 'lunas' : 'menunggu')

const sheetOpen = ref(false)
const selected = ref(null)
const alasan = ref('')
const membalik = ref(false)

function bukaDetail(j) {
    selected.value = j
    alasan.value = ''
    sheetOpen.value = true
}

function balikJurnal() {
    if (!selected.value) return
    membalik.value = true
    router.post(`/akuntan/jurnal/${selected.value.id}/balik`, { alasan: alasan.value }, {
        preserveScroll: true,
        onFinish: () => {
            membalik.value = false
            sheetOpen.value = false
        },
    })
}
</script>

<template>
    <Head title="Jurnal · Akuntan" />

    <AppShell :nav-items="navigation" brand-name="Akuntan">
        <div class="space-y-5">
            <PageHeader kicker="Buku Besar" title="Jurnal Umum" :subtitle="`${meta.total ?? jurnal.length} entri`" />

            <SectionCard flush>
                <EmptyState
                    v-if="!jurnal.length"
                    icon="book-open"
                    title="Belum ada jurnal"
                    message="Jurnal terbit otomatis dari transaksi (payroll, belanja, tagihan, dll)."
                />
                <div v-else class="divide-y divide-slate-100">
                    <button
                        v-for="j in jurnal"
                        :key="j.id"
                        type="button"
                        class="block w-full text-left"
                        @click="bukaDetail(j)"
                    >
                        <ListRow :title="j.deskripsi" :subtitle="`${j.tanggal} · ${j.tipe_sumber ?? '-'}`" chevron>
                            <template #leading><IconCircle icon="book-open" tone="teal" /></template>
                            <template #trailing>
                                <MoneyText :amount="j.total" size="md" />
                                <StatusBadge :status="statusKey(j.status)" :label="statusLabel[j.status] ?? j.status" size="sm" class="mt-1" />
                            </template>
                        </ListRow>
                    </button>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Jurnal terbit otomatis dari transaksi domain (payroll/belanja/tagihan) — tidak ada input manual.
                Jurnal keliru dikoreksi dengan membalikkannya (bukan diedit langsung).
            </p>
        </div>

        <BottomSheet v-model="sheetOpen" title="Detail Jurnal">
            <div v-if="selected" class="space-y-4">
                <div>
                    <p class="text-base font-bold text-slate-900">{{ selected.deskripsi }}</p>
                    <p class="text-sm text-slate-500">{{ selected.tanggal }} · {{ selected.nomor }}</p>
                    <StatusBadge :status="statusKey(selected.status)" :label="statusLabel[selected.status] ?? selected.status" size="sm" class="mt-2" />
                </div>

                <div class="space-y-2 rounded-2xl bg-slate-50 p-4">
                    <div v-for="(b, i) in selected.baris" :key="i" class="flex items-center justify-between text-sm">
                        <span class="min-w-0 truncate text-slate-600">{{ b.akun }}</span>
                        <span class="shrink-0 font-semibold tabular-nums text-slate-900">
                            <template v-if="b.debit > 0">D {{ b.debit.toLocaleString('id-ID') }}</template>
                            <template v-else-if="b.kredit > 0">K {{ b.kredit.toLocaleString('id-ID') }}</template>
                        </span>
                    </div>
                </div>

                <div v-if="can.balik_jurnal && selected.status === 'posted'" class="space-y-2">
                    <UiField label="Alasan pembalikan (opsional)">
                        <template #default="{ fieldId, describedby }">
                            <UiInput
                                :id="fieldId"
                                v-model="alasan"
                                type="text"
                                placeholder="mis. salah akun/nominal"
                                :aria-describedby="describedby"
                            />
                        </template>
                    </UiField>
                </div>
            </div>

            <template #footer>
                <div v-if="can.balik_jurnal && selected?.status === 'posted'" class="flex gap-2">
                    <UiButton variant="ghost" block :disabled="membalik" @click="sheetOpen = false">Tutup</UiButton>
                    <UiButton variant="danger" block :disabled="membalik" @click="balikJurnal">
                        {{ membalik ? 'Membalikkan…' : 'Balikkan Jurnal' }}
                    </UiButton>
                </div>
                <UiButton v-else variant="secondary" block @click="sheetOpen = false">Tutup</UiButton>
            </template>
        </BottomSheet>
    </AppShell>
</template>
