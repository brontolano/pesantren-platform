<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull, formatTanggal } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    ListRow,
    StatCard,
    StatusBadge,
    UiButton,
    MoneyText,
    IconCircle,
    LoadingState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })
const props = defineProps({ can: Object })

const loading = ref(true)
const data = ref(null)

const tutup = ref(null)
const tutupLoading = ref(false)
const tutupPesan = ref(null)

async function muatPratinjau() {
    if (!props.can?.close_period) return
    try { tutup.value = await api.get('/laporan/tutup-buku') } catch { /* */ }
}

async function kunciPeriode() {
    if (!tutup.value?.periode) return
    if (!confirm(`Tutup buku periode ${tutup.value.periode}? Semua akun pendapatan & beban akan di-nol-kan ke Aset Neto dan periode DIKUNCI.`)) return
    const ketik = prompt('Ketik TUTUP untuk konfirmasi akhir:')
    if (ketik !== 'TUTUP') return

    tutupLoading.value = true
    tutupPesan.value = null
    try {
        const res = await api.post('/laporan/tutup-buku', { konfirmasi: 'TUTUP' })
        tutupPesan.value = { ok: true, teks: res.message }
        await muatPratinjau()
    } catch (e) {
        tutupPesan.value = { ok: false, teks: e.message }
    } finally {
        tutupLoading.value = false
    }
}

onMounted(async () => {
    try { data.value = await api.get('/laporan/arus-kas') } catch { /* */ }
    loading.value = false
    muatPratinjau()
})
</script>

<template>
    <div class="space-y-5">
        <PageHeader kicker="Laporan" title="Arus Kas & Tutup Buku" />

        <LoadingState v-if="loading" variant="list" :count="5" />

        <template v-else-if="data">
            <SectionCard
                v-for="section in data.sections ?? []"
                :key="section.nama"
                :title="section.nama"
                flush
            >
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="row in section.items ?? []" :key="row.nama" :title="row.nama">
                        <template #trailing>
                            <MoneyText
                                :amount="row.saldo"
                                size="sm"
                                :tone="row.saldo < 0 ? 'rose' : 'default'"
                            />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <StatCard
                v-if="data.netCashFlow != null"
                icon="chart"
                :tone="data.netCashFlow < 0 ? 'rose' : 'emerald'"
                label="Arus Kas Bersih"
            >
                <template #value>
                    <MoneyText
                        :amount="data.netCashFlow"
                        size="xl"
                        :tone="data.netCashFlow < 0 ? 'rose' : 'emerald'"
                    />
                </template>
            </StatCard>

            <!-- Tutup Buku -->
            <SectionCard v-if="props.can?.close_period" kicker="Periode Akuntansi" title="Tutup Buku">
                <div v-if="!tutup" class="py-2">
                    <LoadingState variant="list" :count="3" />
                </div>

                <div v-else-if="!tutup.periode" class="flex items-center gap-3 text-sm text-slate-500">
                    <IconCircle icon="info" tone="slate" size="sm" />
                    Tidak ada periode akuntansi yang terbuka.
                </div>

                <template v-else>
                    <div class="divide-y divide-slate-100">
                        <ListRow title="Periode">
                            <template #trailing>
                                <span class="text-sm font-bold tabular-nums text-slate-900">{{ tutup.periode }}</span>
                            </template>
                        </ListRow>
                        <ListRow title="Rentang">
                            <template #trailing>
                                <span class="text-sm text-slate-700 tabular-nums">{{ formatTanggal(tutup.tgl_mulai) }} – {{ formatTanggal(tutup.tgl_selesai) }}</span>
                            </template>
                        </ListRow>
                        <ListRow title="Surplus / Defisit">
                            <template #trailing>
                                <MoneyText
                                    :amount="tutup.surplus_defisit"
                                    size="sm"
                                    :tone="tutup.surplus_defisit >= 0 ? 'emerald' : 'rose'"
                                />
                            </template>
                        </ListRow>
                        <ListRow title="Baris jurnal penutup">
                            <template #trailing>
                                <span class="text-sm font-semibold tabular-nums text-slate-900">{{ tutup.jumlah_baris }}</span>
                            </template>
                        </ListRow>
                    </div>

                    <div
                        v-if="tutupPesan"
                        class="mt-3 rounded-2xl px-3 py-2 text-sm"
                        :class="tutupPesan.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                    >
                        {{ tutupPesan.teks }}
                    </div>

                    <div class="mt-4">
                        <UiButton
                            v-if="!tutup.sudah_ditutup"
                            block
                            :loading="tutupLoading"
                            :disabled="tutupLoading"
                            icon-left="ban"
                            @click="kunciPeriode"
                        >
                            {{ tutupLoading ? 'Memproses…' : 'Kunci Periode ' + tutup.periode }}
                        </UiButton>
                        <div v-else class="flex items-center justify-center gap-2 rounded-2xl bg-emerald-50 px-3 py-3 text-sm font-semibold text-emerald-700">
                            <StatusBadge status="aktif" label="Periode sudah ditutup" size="sm" />
                        </div>
                    </div>

                    <p class="mt-3 text-[11px] leading-relaxed text-slate-500">
                        Tutup buku memposting jurnal penutup (pendapatan & beban → Aset Neto) lalu mengunci periode dari posting baru. Pembukaan kembali hanya lewat panel admin.
                    </p>
                </template>
            </SectionCard>
        </template>
    </div>
</template>
