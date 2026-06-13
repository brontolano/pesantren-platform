<script setup>
import { Head } from '@inertiajs/vue3'
import { onMounted, onUnmounted, ref } from 'vue'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, ListRow, StatusBadge, Chip, EmptyState, IconCircle } from '@/Shared'

defineOptions({ layout: PortalGuruLayout })

const props = defineProps({
    sesi: { type: Object, required: true },
    attempts: { type: Array, default: () => [] },
})

const daftar = ref(props.attempts)
let timer = null

async function muatUlang() {
    try {
        const res = await fetch(`/guru/ujian/${props.sesi.id}/pengawas/data`, { headers: { Accept: 'application/json' } })
        if (res.ok) {
            const json = await res.json()
            daftar.value = json.attempts
        }
    } catch { /* offline — biarkan data lama */ }
}

onMounted(() => { timer = setInterval(muatUlang, 10000) }) // polling 10 dtk
onUnmounted(() => clearInterval(timer))

const sisa = (detik) => {
    const m = Math.floor(detik / 60)
    const s = detik % 60
    return `${m}:${String(s).padStart(2, '0')}`
}

const badge = (status) => ({
    berjalan: 'bg-emerald-100 text-emerald-700',
    menunggu_nilai: 'bg-amber-100 text-amber-700',
    selesai: 'bg-slate-200 text-slate-600',
}[status] ?? 'bg-slate-100 text-slate-500')
</script>

<template>
    <div class="space-y-5">
        <Head><title>Pengawas Ujian</title></Head>

        <PageHeader
            kicker="Mode Pengawas"
            :title="sesi.nama"
            :subtitle="`${sesi.pelajaran} · ${sesi.kelas} — daftar peserta diperbarui otomatis.`"
        >
            <template #right>
                <Chip :active="true" tone="brand" size="sm" icon="wifi-off">Live</Chip>
            </template>
        </PageHeader>

        <SectionCard v-if="daftar.length === 0" flush>
            <EmptyState
                icon="user"
                title="Belum ada santri yang mulai"
                message="Daftar peserta akan muncul otomatis saat santri membuka ujian."
            />
        </SectionCard>

        <SectionCard v-else flush>
            <div class="divide-y divide-slate-100">
                <ListRow v-for="a in daftar" :key="a.id">
                    <template #leading>
                        <IconCircle icon="user" tone="slate" variant="tint" size="sm" />
                    </template>
                    <template #title>{{ a.santri }}</template>
                    <template #subtitle>
                        NIS {{ a.nis }} · mulai {{ a.mulai_at }} · {{ a.terjawab }}/{{ a.total_soal }} terjawab
                    </template>
                    <template #trailing>
                        <div class="text-right">
                            <StatusBadge
                                :status="{ berjalan: 'aktif', menunggu_nilai: 'menunggu', selesai: 'batal' }[a.status] ?? 'info'"
                                :label="a.status"
                                size="sm"
                            />
                            <p v-if="a.status === 'berjalan'" class="mt-1 font-mono text-xs tabular-nums text-slate-600">sisa {{ sisa(a.sisa_detik) }}</p>
                            <p v-else-if="a.selesai_at" class="mt-1 text-xs text-slate-400">selesai {{ a.selesai_at }}</p>
                        </div>
                    </template>
                </ListRow>
            </div>
        </SectionCard>
    </div>
</template>
