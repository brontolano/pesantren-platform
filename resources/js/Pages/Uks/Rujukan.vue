<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { AppShell, PageHeader, StatCard, SectionCard, ListRow, StatusBadge, MoneyText, IconCircle, EmptyState, UiButton, UiField, UiInput, BottomSheet } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    rujukan: { type: Array, default: () => [] },
})

const statusLabel = { menunggu: 'Menunggu', terverifikasi: 'Selesai' }

const totalBiaya = computed(() => props.rujukan.reduce((sum, r) => sum + (r.biaya || 0), 0))
const terbuka = computed(() => props.rujukan.filter((r) => r.status === 'menunggu').length)

const sheetOpen = ref(false)
const rujukanDipilih = ref(null)
const tujuan = ref('')
const biaya = ref(0)
const mengirim = ref(false)

function bukaForm(r) {
    rujukanDipilih.value = r
    tujuan.value = r.tujuan === '-' ? '' : r.tujuan
    biaya.value = r.biaya || 0
    sheetOpen.value = true
}

function simpanDetail() {
    if (!rujukanDipilih.value) return
    mengirim.value = true
    router.post(`/uks/rujukan/${rujukanDipilih.value.id}`, {
        rujukan_ke: tujuan.value,
        rujukan_biaya: biaya.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            mengirim.value = false
            sheetOpen.value = false
        },
    })
}

function tutupRujukan(r) {
    router.post(`/uks/kunjungan/${r.id}/tutup`, {}, { preserveScroll: true })
}
</script>

<template>
    <Head title="Rujukan UKS" />

    <AppShell :nav-items="navigation" brand-name="Kesehatan UKS">
        <div class="space-y-5">
            <PageHeader kicker="Layanan UKS" title="Rujukan" subtitle="Santri dirujuk ke faskes luar" />

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="inbox" tone="rose" label="Rujukan Terbuka" :value="String(terbuka)" hint="menunggu jemputan" />
                <StatCard icon="cash" tone="emerald" label="Total Biaya">
                    <template #value>
                        <MoneyText :amount="totalBiaya" size="lg" tone="emerald" />
                    </template>
                </StatCard>
            </div>

            <SectionCard flush>
                <EmptyState
                    v-if="!rujukan.length"
                    icon="inbox"
                    title="Tidak ada rujukan"
                    message="Santri yang dirujuk ke Puskesmas atau RS akan tampil di sini."
                />
                <div v-else class="divide-y divide-slate-100">
                    <ListRow
                        v-for="r in rujukan"
                        :key="r.id"
                        :title="r.nama"
                        :subtitle="`${r.kelas} • ${r.tujuan}`"
                    >
                        <template #leading>
                            <IconCircle icon="bank" tone="blue" size="md" />
                        </template>
                        <template #trailing>
                            <MoneyText :amount="r.biaya" size="sm" tone="default" />
                        </template>

                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate text-sm text-slate-500">{{ r.keluhan }}</span>
                            <StatusBadge :status="r.status" :label="statusLabel[r.status]" size="sm" />
                        </div>

                        <div v-if="can.kelola_rujukan && r.status === 'menunggu'" class="mt-2.5 flex gap-2">
                            <UiButton variant="secondary" size="sm" block @click="bukaForm(r)">
                                {{ r.tujuan === '-' ? 'Isi Tujuan' : 'Ubah Detail' }}
                            </UiButton>
                            <UiButton variant="primary" size="sm" block @click="tutupRujukan(r)">
                                Tutup Rujukan
                            </UiButton>
                        </div>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Biaya rujukan tercatat untuk klaim ke wali / dana kesehatan pesantren.
            </p>
        </div>

        <BottomSheet v-model="sheetOpen" :title="`Detail Rujukan: ${rujukanDipilih?.nama ?? ''}`">
            <div v-if="rujukanDipilih" class="space-y-3">
                <UiField label="Tujuan rujukan (Puskesmas/RS)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            v-model="tujuan"
                            type="text"
                            placeholder="mis. Puskesmas Cileunyi"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>
                <UiField label="Estimasi biaya (Rp)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            v-model.number="biaya"
                            type="number"
                            min="0"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block :disabled="mengirim" @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block :disabled="mengirim || !tujuan" @click="simpanDetail">
                        Simpan
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
