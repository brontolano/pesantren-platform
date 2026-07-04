<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { AppShell, PageHeader, StatCard, SectionCard, ListRow, StatusBadge, Chip, IconCircle, UiButton, UiField, UiInput, BottomSheet } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    obat: { type: Array, default: () => [] },
})

const menipis = (o) => o.stok <= o.ambang_menipis

const totalItem = computed(() => props.obat.length)
const jumlahMenipis = computed(() => props.obat.filter(menipis).length)

const sheetOpen = ref(false)
const obatDipilih = ref(null)
const jumlah = ref(1)
const keterangan = ref('')
const mengirim = ref(false)

function bukaForm(o) {
    obatDipilih.value = o
    jumlah.value = 1
    keterangan.value = ''
    sheetOpen.value = true
}

function simpanPakai() {
    if (!obatDipilih.value) return
    mengirim.value = true
    router.post(`/uks/obat/${obatDipilih.value.id}/pakai`, {
        jumlah: jumlah.value,
        keterangan: keterangan.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            mengirim.value = false
            sheetOpen.value = false
        },
    })
}
</script>

<template>
    <Head title="Stok Obat UKS" />

    <AppShell :nav-items="navigation" brand-name="Kesehatan UKS">
        <div class="space-y-5">
            <PageHeader kicker="Apotek UKS" title="Stok Obat" subtitle="Persediaan obat & alat kesehatan" />

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="receipt" tone="blue" label="Total Item" :value="String(totalItem)" hint="jenis obat" />
                <StatCard icon="info" tone="amber" label="Perlu Restock" :value="String(jumlahMenipis)" hint="stok ≤ 5" />
            </div>

            <SectionCard flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="o in obat"
                        :key="o.id"
                        :title="o.nama"
                    >
                        <template #leading>
                            <IconCircle icon="receipt" :tone="menipis(o.stok) ? 'amber' : 'teal'" size="md" />
                        </template>
                        <template #subtitle>
                            <span class="inline-flex items-center gap-2">
                                <Chip :active="false" tone="soft" size="sm">{{ o.satuan }}</Chip>
                            </span>
                        </template>
                        <template #trailing>
                            <div class="flex flex-col items-end gap-1.5">
                                <span class="text-lg font-extrabold tabular-nums text-slate-900">{{ o.stok }}</span>
                                <StatusBadge v-if="menipis(o)" status="info" label="Menipis" size="sm" />
                                <UiButton v-if="can.kelola_obat" variant="ghost" size="sm" @click="bukaForm(o)">
                                    Catat Pakai
                                </UiButton>
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Obat ditandai "Menipis" saat stok sudah di ambang batas — segera ajukan pembelian ke bendahara.
            </p>
        </div>

        <BottomSheet v-model="sheetOpen" :title="`Catat Pakai: ${obatDipilih?.nama ?? ''}`">
            <div v-if="obatDipilih" class="space-y-3">
                <p class="text-sm text-slate-500">Sisa stok saat ini: <strong>{{ obatDipilih.stok }} {{ obatDipilih.satuan }}</strong></p>
                <UiField label="Jumlah dipakai">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            v-model.number="jumlah"
                            type="number"
                            min="1"
                            :max="obatDipilih.stok"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>
                <UiField label="Keterangan (opsional)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            v-model="keterangan"
                            type="text"
                            placeholder="mis. untuk santri demam di kunjungan hari ini"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block :disabled="mengirim" @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block :disabled="mengirim || jumlah < 1" @click="simpanPakai">
                        Simpan
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
