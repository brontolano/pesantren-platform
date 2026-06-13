<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatWaktu } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    UiCard,
    ListRow,
    IconCircle,
    StatusBadge,
    MoneyText,
    UiButton,
    UiField,
    UiInput,
    SegmentedControl,
    EmptyState,
    LoadingState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })
const props = defineProps({ can: Object })

const loading = ref(true)
const sesiAktif = ref(null)
const riwayat = ref([])
const mode = ref('supervisi')

const formTerima = ref({ nama: '', keterangan: '', nominal: '' })
const submitting = ref(false)

async function fetchData() {
    loading.value = true
    try {
        const res = await api.get('/loket')
        sesiAktif.value = res.sesi ?? null
        riwayat.value = res.transaksi ?? []
    } catch { /* */ }
    loading.value = false
}

async function bukaSesi() {
    submitting.value = true
    try {
        await api.post('/loket/buka-sesi')
        await fetchData()
    } catch { /* */ }
    submitting.value = false
}

async function tutupSesi() {
    if (!confirm('Tutup sesi kasir? Sesi tidak bisa dibuka kembali.')) return
    submitting.value = true
    try {
        await api.post('/loket/tutup-sesi')
        await fetchData()
    } catch { /* */ }
    submitting.value = false
}

async function terimaTunai() {
    if (!formTerima.value.nominal || submitting.value) return
    submitting.value = true
    try {
        await api.post('/loket/terima-tunai', {
            nama: formTerima.value.nama,
            keterangan: formTerima.value.keterangan,
            nominal: parseFloat(formTerima.value.nominal),
        })
        formTerima.value = { nama: '', keterangan: '', nominal: '' }
        await fetchData()
    } catch { /* */ }
    submitting.value = false
}

onMounted(fetchData)
</script>

<template>
    <div class="space-y-5">
        <PageHeader
            kicker="Penerimaan"
            title="Loket Kas"
            subtitle="Buka sesi kasir dan catat penerimaan tunai"
        />

        <SegmentedControl
            v-if="props.can?.manage_keuangan"
            :model-value="mode"
            :options="[
                { value: 'supervisi', label: 'Mode Supervisi', icon: 'user' },
                { value: 'kasir', label: 'Mode Kasir', icon: 'cash' },
            ]"
            @update:model-value="mode = $event"
        />

        <!-- Skeleton -->
        <LoadingState v-if="loading" variant="card" :count="2" />

        <template v-else>
            <!-- Sesi info -->
            <UiCard padding="md">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <StatusBadge
                            :status="sesiAktif ? 'aktif' : 'batal'"
                            :label="sesiAktif ? 'Sesi Aktif' : 'Tidak Ada Sesi'"
                            size="sm"
                        />
                        <p v-if="sesiAktif" class="mt-2 text-sm text-slate-500">
                            Dibuka {{ formatWaktu(sesiAktif.dibuka_pada) }} · Petugas: {{ sesiAktif.petugas }}
                        </p>
                        <MoneyText
                            v-if="sesiAktif"
                            :amount="sesiAktif.total ?? 0"
                            size="lg"
                            tone="emerald"
                            class="mt-1 block"
                        />
                    </div>
                    <UiButton
                        v-if="mode === 'kasir'"
                        :variant="sesiAktif ? 'danger' : 'primary'"
                        size="sm"
                        :loading="submitting"
                        :disabled="submitting"
                        :iconLeft="sesiAktif ? 'x-circle' : 'check'"
                        @click="sesiAktif ? tutupSesi() : bukaSesi()"
                    >
                        {{ sesiAktif ? 'Tutup Sesi' : 'Buka Sesi' }}
                    </UiButton>
                </div>
            </UiCard>

            <!-- Form terima tunai (kasir mode + sesi aktif) -->
            <SectionCard
                v-if="mode === 'kasir' && sesiAktif"
                kicker="Tunai"
                title="Terima Pembayaran Tunai"
            >
                <div class="space-y-3">
                    <UiField label="Nama santri / pembayar">
                        <template #default="{ fieldId, describedby }">
                            <UiInput
                                :id="fieldId"
                                :aria-describedby="describedby"
                                v-model="formTerima.nama"
                                placeholder="Nama santri / pembayar"
                            />
                        </template>
                    </UiField>
                    <UiField label="Keterangan">
                        <template #default="{ fieldId, describedby }">
                            <UiInput
                                :id="fieldId"
                                :aria-describedby="describedby"
                                v-model="formTerima.keterangan"
                                placeholder="Keterangan (SPP, Infaq, dll)"
                            />
                        </template>
                    </UiField>
                    <UiField label="Nominal (Rp)">
                        <template #default="{ fieldId, describedby }">
                            <UiInput
                                :id="fieldId"
                                :aria-describedby="describedby"
                                v-model="formTerima.nominal"
                                type="number"
                                inputmode="numeric"
                                placeholder="Nominal (Rp)"
                            />
                        </template>
                    </UiField>
                    <UiButton
                        variant="primary"
                        block
                        iconLeft="receipt"
                        :loading="submitting"
                        :disabled="submitting || !formTerima.nominal"
                        @click="terimaTunai"
                    >
                        Terima &amp; Cetak Kuitansi
                    </UiButton>
                </div>
            </SectionCard>

            <!-- Riwayat transaksi sesi -->
            <SectionCard v-if="riwayat.length" kicker="Sesi ini" title="Riwayat Transaksi" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="t in riwayat" :key="t.id">
                        <template #leading>
                            <IconCircle icon="cash" tone="emerald" />
                        </template>
                        <template #title>{{ t.nama ?? t.keterangan }}</template>
                        <template #subtitle>{{ formatWaktu(t.waktu) }} · {{ t.keterangan }}</template>
                        <template #trailing>
                            <MoneyText :amount="t.nominal" size="md" tone="emerald" sign="plus" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
            <div v-else class="space-y-3">
                <PageHeader kicker="Sesi ini" title="Riwayat Transaksi" />
                <EmptyState
                    icon="inbox"
                    title="Belum ada transaksi"
                    message="Belum ada transaksi di sesi ini."
                />
            </div>
        </template>
    </div>
</template>
