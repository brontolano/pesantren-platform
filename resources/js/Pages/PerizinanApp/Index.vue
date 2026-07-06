<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, IconCircle, UiButton, EmptyState, BottomSheet } from '@/Shared'

const props = defineProps({
    daftar: { type: Array, default: () => [] },
    bolehTulis: { type: Boolean, default: false },
    opsiJenis: { type: Array, default: () => [] },
    santriOptions: { type: Array, default: () => [] },
})

const nav = [
    { key: 'beranda', label: 'Beranda', href: '/staf', active: false },
    { key: 'izin', label: 'Izin', href: '/perizinan-app', active: true },
]

const buka = ref(false)
const form = useForm({ santri_id: '', jenis_izin: 'pulang', tanggal_mulai: '', tanggal_selesai: '', alasan: '' })
function simpan() {
    form.post('/perizinan-app', { preserveScroll: true, onSuccess: () => { buka.value = false; form.reset() } })
}
function tandaiKembali(i) {
    router.post(`/perizinan-app/${i.id}/kembali`, {}, { preserveScroll: true })
}

const jenisClass = { pulang: 'bg-emerald-100 text-emerald-700', sakit: 'bg-amber-100 text-amber-700', keluar_kota: 'bg-sky-100 text-sky-700', kunjungan: 'bg-violet-100 text-violet-700' }
const inputClass = 'w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-[var(--brand-primary)] focus:ring-2 focus:ring-[var(--brand-primary)]/20'
</script>

<template>
    <AppShell :nav-items="nav" brand-name="Izin Santri">
        <Head><title>Izin Santri</title></Head>
        <div class="space-y-5">
            <PageHeader kicker="Perizinan & Kunjungan" title="Izin Santri" subtitle="Catat izin & tandai santri yang sudah kembali." />

            <UiButton v-if="bolehTulis" variant="primary" size="lg" block @click="buka = true">+ Catat Izin Baru</UiButton>

            <EmptyState v-if="daftar.length === 0" icon="document" title="Belum ada izin" message="Izin santri akan tampil di sini." />

            <SectionCard v-else title="Daftar Izin" :subtitle="`${daftar.length} terbaru`" flush>
                <div class="divide-y divide-slate-100">
                    <div v-for="i in daftar" :key="i.id" class="px-5 py-4">
                        <div class="flex items-start gap-3">
                            <IconCircle icon="document" tone="emerald" variant="tint" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-slate-900">{{ i.santri }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ i.kelas }} · {{ i.mulai }} – {{ i.selesai }}</p>
                                <p class="mt-0.5 text-xs text-slate-600">{{ i.alasan }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold capitalize" :class="jenisClass[i.jenis] || 'bg-slate-100 text-slate-600'">{{ i.jenis.replace('_', ' ') }}</span>
                        </div>
                        <div class="mt-2 flex items-center gap-2 pl-11">
                            <span v-if="i.sudah_kembali" class="text-xs font-semibold text-emerald-600">✓ Sudah kembali</span>
                            <button v-else-if="bolehTulis" type="button" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700 active:scale-95" @click="tandaiKembali(i)">Tandai Kembali</button>
                            <span v-else class="text-xs font-semibold text-amber-600">Belum kembali</span>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </div>

        <BottomSheet v-model="buka" title="Catat Izin Baru">
            <form class="space-y-3.5" @submit.prevent="simpan">
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Santri
                    <select v-model="form.santri_id" required :class="inputClass">
                        <option value="" disabled>Pilih santri…</option>
                        <option v-for="s in santriOptions" :key="s.id" :value="s.id">{{ s.label }}</option>
                    </select>
                </label>
                <p v-if="form.errors.santri_id" class="text-xs font-medium text-rose-600">{{ form.errors.santri_id }}</p>

                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Jenis Izin
                    <select v-model="form.jenis_izin" :class="inputClass">
                        <option v-for="j in opsiJenis" :key="j" :value="j" class="capitalize">{{ j.replace('_', ' ') }}</option>
                    </select>
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <label class="grid gap-1.5 text-sm font-bold text-slate-700">Mulai
                        <input v-model="form.tanggal_mulai" type="date" required :class="inputClass">
                    </label>
                    <label class="grid gap-1.5 text-sm font-bold text-slate-700">Selesai
                        <input v-model="form.tanggal_selesai" type="date" required :class="inputClass">
                    </label>
                </div>
                <p v-if="form.errors.tanggal_selesai" class="text-xs font-medium text-rose-600">{{ form.errors.tanggal_selesai }}</p>

                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Alasan
                    <textarea v-model="form.alasan" rows="2" required maxlength="500" :class="inputClass" placeholder="Keperluan izin"></textarea>
                </label>
                <p v-if="form.errors.alasan" class="text-xs font-medium text-rose-600">{{ form.errors.alasan }}</p>

                <UiButton type="submit" variant="primary" size="lg" block :loading="form.processing" :disabled="form.processing">Simpan Izin</UiButton>
            </form>
        </BottomSheet>
    </AppShell>
</template>
