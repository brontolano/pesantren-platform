<script setup>
import { onMounted, ref, watch } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    StatCard,
    ListRow,
    Chip,
    MoneyText,
    EmptyState,
    LoadingState,
    IconCircle,
    UiIcon,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })

const tabs = [
    { key: 'bos-bop', label: 'BOS/BOP' },
    { key: 'kebajikan', label: 'Kebajikan' },
    { key: 'terikat', label: 'Terikat' },
    { key: 'divisi', label: 'Per Divisi' },
]

const aktif = ref(new URLSearchParams(window.location.search).get('tab') ?? 'bos-bop')
const loading = ref(true)
const data = ref(null)
const error = ref(null)

async function muat() {
    loading.value = true
    error.value = null
    try {
        data.value = await api.get(`/laporan/dana?jenis=${aktif.value}`)
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

watch(aktif, muat)
onMounted(muat)

const labelTerikat = { wakaf: 'Wakaf (PSAK 112)', zis: 'Zakat-Infak-Sedekah (PSAK 109)', lainnya_terikat: 'Dana Terikat Lainnya' }
</script>

<template>
    <div class="space-y-5">
        <PageHeader kicker="Laporan" title="Dana & Realisasi" subtitle="Posisi dana per kategori sumber" />

        <div class="flex flex-wrap gap-2">
            <Chip
                v-for="t in tabs"
                :key="t.key"
                :active="aktif === t.key"
                :icon="{ 'bos-bop': 'bank', 'kebajikan': 'heart', 'terikat': 'wallet', 'divisi': 'chart' }[t.key]"
                size="sm"
                @click="aktif = t.key"
            >
                {{ t.label }}
            </Chip>
        </div>

        <LoadingState v-if="loading" variant="card" :count="3" />

        <div
            v-else-if="error"
            class="flex items-start gap-2 rounded-3xl bg-rose-50 px-4 py-4 text-sm text-rose-700 ring-1 ring-rose-100"
        >
            <UiIcon name="x-circle" class="mt-0.5 h-4 w-4 shrink-0" />
            <span>Gagal memuat: {{ error }}</span>
        </div>

        <template v-else-if="data">
            <!-- BOS/BOP -->
            <template v-if="aktif === 'bos-bop'">
                <SectionCard v-if="data.length" title="Sumber dana negara" subtitle="BOS / BOP" flush>
                    <div class="divide-y divide-slate-100">
                        <ListRow v-for="d in data" :key="d.kode">
                            <template #leading>
                                <IconCircle icon="bank" tone="emerald" />
                            </template>
                            <template #title>{{ d.nama }}</template>
                            <template #subtitle>
                                {{ d.kode }}<span v-if="d.rezim"> · {{ d.rezim.replaceAll('_', ' ').toUpperCase() }}</span>
                            </template>
                            <template #trailing>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold tabular-nums ring-1 ring-inset"
                                    :class="d.sisa >= 0 ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-rose-50 text-rose-700 ring-rose-100'"
                                >
                                    sisa <MoneyText :amount="d.sisa" size="sm" tone="inherit" />
                                </span>
                            </template>
                            <div class="grid grid-cols-2 gap-3 pt-1">
                                <div>
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Penerimaan</span>
                                    <div><MoneyText :amount="d.penerimaan" size="sm" tone="emerald" /></div>
                                </div>
                                <div>
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Penggunaan</span>
                                    <div><MoneyText :amount="d.penggunaan" size="sm" tone="rose" /></div>
                                </div>
                            </div>
                        </ListRow>
                    </div>
                </SectionCard>
                <EmptyState
                    v-else
                    icon="bank"
                    title="Belum ada sumber dana"
                    message="Belum ada sumber dana kategori negara"
                />
            </template>

            <!-- Dana Kebajikan -->
            <template v-else-if="aktif === 'kebajikan'">
                <div class="flex items-start gap-2 rounded-3xl bg-amber-50 px-4 py-4 text-xs leading-5 text-amber-800 ring-1 ring-amber-100">
                    <UiIcon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>Dana kebajikan (penerimaan syubhat) disajikan terpisah dari surplus dan hanya boleh disalurkan untuk fasilitas umum.</span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <StatCard tone="emerald" icon="arrow-down-left" label="Penerimaan">
                        <template #value>
                            <MoneyText :amount="data.penerimaan" size="lg" tone="emerald" />
                        </template>
                    </StatCard>
                    <StatCard tone="teal" icon="wallet" label="Saldo">
                        <template #value>
                            <MoneyText :amount="data.saldo" size="lg" tone="emerald" />
                        </template>
                    </StatCard>
                </div>

                <SectionCard v-if="data.penyaluran?.length" title="Penyaluran" flush>
                    <div class="divide-y divide-slate-100">
                        <ListRow v-for="p in data.penyaluran" :key="p.kode">
                            <template #leading>
                                <IconCircle icon="heart" tone="rose" />
                            </template>
                            <template #title>{{ p.nama }}</template>
                            <template #subtitle>
                                {{ p.kode }}<span v-if="p.peruntukan"> · {{ p.peruntukan }}</span>
                            </template>
                            <template #trailing>
                                <MoneyText :amount="p.jumlah" size="sm" tone="rose" />
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>
                <EmptyState
                    v-else
                    icon="heart"
                    title="Belum ada penyaluran"
                    message="Belum ada penyaluran dana kebajikan"
                />
            </template>

            <!-- Dana Terikat -->
            <template v-else-if="aktif === 'terikat'">
                <template v-for="(rows, kunci) in data" :key="kunci">
                    <SectionCard v-if="rows.length" :title="labelTerikat[kunci] ?? kunci" flush>
                        <div class="divide-y divide-slate-100">
                            <ListRow v-for="d in rows" :key="d.kode">
                                <template #leading>
                                    <IconCircle icon="wallet" tone="emerald" />
                                </template>
                                <template #title>{{ d.nama }}</template>
                                <template #trailing>
                                    <MoneyText :amount="d.saldo" size="sm" tone="emerald" />
                                </template>
                                <div class="flex justify-between pt-1 text-xs text-slate-500">
                                    <span class="tabular-nums">Masuk {{ formatRupiah(d.penerimaan) }}</span>
                                    <span class="tabular-nums">Pakai {{ formatRupiah(d.penggunaan) }}</span>
                                </div>
                            </ListRow>
                        </div>
                    </SectionCard>
                    <SectionCard v-else :title="labelTerikat[kunci] ?? kunci">
                        <p class="text-center text-xs text-slate-400">Tidak ada</p>
                    </SectionCard>
                </template>
            </template>

            <!-- Realisasi per Divisi -->
            <template v-else>
                <SectionCard v-if="data.length" title="Realisasi per divisi" flush>
                    <div class="divide-y divide-slate-100">
                        <ListRow v-for="d in data" :key="d.divisi">
                            <template #leading>
                                <IconCircle icon="chart" tone="blue" />
                            </template>
                            <template #title>{{ d.divisi }}</template>
                            <template #trailing>
                                <MoneyText :amount="d.realisasi" size="sm" tone="emerald" />
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>
                <EmptyState
                    v-else
                    icon="chart"
                    title="Belum ada realisasi"
                    message="Belum ada realisasi beban"
                />
            </template>
        </template>
    </div>
</template>
