<script setup>
import { Head } from '@inertiajs/vue3'
import { onMounted, onUnmounted, ref } from 'vue'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

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

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Mode Pengawas</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ sesi.nama }}</h2>
            <p class="text-sm text-slate-500">{{ sesi.pelajaran }} · {{ sesi.kelas }} — daftar peserta diperbarui otomatis.</p>
        </section>

        <section v-if="daftar.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada santri yang mulai</p>
        </section>

        <section v-else class="space-y-2">
            <div v-for="a in daftar" :key="a.id" class="flex items-center justify-between gap-3 rounded-2xl bg-white px-4 py-3 shadow-sm">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ a.santri }}</p>
                    <p class="text-xs text-slate-400">NIS {{ a.nis }} · mulai {{ a.mulai_at }} · {{ a.terjawab }}/{{ a.total_soal }} terjawab</p>
                </div>
                <div class="shrink-0 text-right">
                    <span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="badge(a.status)">{{ a.status }}</span>
                    <p v-if="a.status === 'berjalan'" class="mt-1 font-mono text-xs text-slate-600">sisa {{ sisa(a.sisa_detik) }}</p>
                    <p v-else-if="a.selesai_at" class="mt-1 text-xs text-slate-400">selesai {{ a.selesai_at }}</p>
                </div>
            </div>
        </section>
    </div>
</template>
