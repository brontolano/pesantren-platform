<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, IconCircle, UiButton, EmptyState, BottomSheet } from '@/Shared'

const props = defineProps({
    daftar: { type: Array, default: () => [] },
    q: { type: String, default: '' },
    bolehTulis: { type: Boolean, default: false },
    opsiJenis: { type: Array, default: () => [] },
    opsiStatus: { type: Array, default: () => [] },
    pjOptions: { type: Array, default: () => [] },
})

// Nav minimal: kembali ke shell staf (satu aplikasi, peran = grup menu).
const nav = [
    { key: 'beranda', label: 'Beranda', href: '/staf', active: false },
    { key: 'kegiatan', label: 'Kegiatan', href: '/kegiatan-app', active: true },
]

const cari = ref(props.q)
function submitCari() {
    router.get('/kegiatan-app', { q: cari.value }, { preserveState: true, preserveScroll: true })
}

const buka = ref(false)
const form = useForm({
    nama: '', jenis: 'umum', mulai: '', selesai: '', lokasi: '',
    status: 'terjadwal', penanggung_jawab_id: '', deskripsi: '',
})
function simpan() {
    form.transform((d) => ({ ...d, penanggung_jawab_id: d.penanggung_jawab_id || null, selesai: d.selesai || null }))
        .post('/kegiatan-app', {
            preserveScroll: true,
            onSuccess: () => { buka.value = false; form.reset() },
        })
}

// Warna status kegiatan (StatusBadge tak mengenal status ini → chip manual).
const statusClass = {
    terjadwal: 'bg-sky-100 text-sky-700',
    berlangsung: 'bg-amber-100 text-amber-700',
    selesai: 'bg-emerald-100 text-emerald-700',
    dibatalkan: 'bg-rose-100 text-rose-700',
}
function ubahStatus(k, status) {
    router.post(`/kegiatan-app/${k.id}/status`, { status }, { preserveScroll: true })
}

const inputClass = 'w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-[var(--brand-primary)] focus:ring-2 focus:ring-[var(--brand-primary)]/20'
</script>

<template>
    <AppShell :nav-items="nav" brand-name="Kegiatan">
        <Head><title>Kegiatan</title></Head>
        <div class="space-y-5">
            <PageHeader kicker="Kegiatan" title="Agenda Pesantren" subtitle="Kelola kegiatan: jadwal, status, penanggung jawab." />

            <form class="flex gap-2" @submit.prevent="submitCari">
                <input v-model="cari" type="search" placeholder="Cari kegiatan…" :class="inputClass" />
                <UiButton type="submit" variant="secondary">Cari</UiButton>
            </form>

            <UiButton v-if="bolehTulis" variant="primary" size="lg" block @click="buka = true">+ Catat Kegiatan Baru</UiButton>

            <EmptyState v-if="daftar.length === 0" icon="calendar" title="Belum ada kegiatan" message="Kegiatan yang tercatat akan tampil di sini." />

            <SectionCard v-else title="Daftar Kegiatan" :subtitle="`${daftar.length} kegiatan terbaru`" flush>
                <div class="divide-y divide-slate-100">
                    <div v-for="k in daftar" :key="k.id" class="px-5 py-4">
                        <div class="flex items-start gap-3">
                            <IconCircle icon="calendar" tone="emerald" variant="tint" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-slate-900">{{ k.nama }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    <span class="capitalize">{{ k.jenis }}</span>
                                    · {{ k.mulai }}<span v-if="k.selesai"> – {{ k.selesai }}</span>
                                    <span v-if="k.lokasi"> · {{ k.lokasi }}</span>
                                </p>
                                <p v-if="k.pj" class="mt-0.5 text-xs text-slate-500">PJ: {{ k.pj }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold capitalize" :class="statusClass[k.status] || 'bg-slate-100 text-slate-600'">{{ k.status }}</span>
                        </div>
                        <div v-if="bolehTulis && k.status !== 'selesai' && k.status !== 'dibatalkan'" class="mt-2 flex gap-2 pl-11">
                            <button v-if="k.status === 'terjadwal'" type="button" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700 active:scale-95" @click="ubahStatus(k, 'berlangsung')">Mulai</button>
                            <button type="button" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700 active:scale-95" @click="ubahStatus(k, 'selesai')">Selesai</button>
                            <button type="button" class="rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-700 active:scale-95" @click="ubahStatus(k, 'dibatalkan')">Batalkan</button>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </div>

        <BottomSheet v-model="buka" title="Catat Kegiatan Baru">
            <form class="space-y-3.5" @submit.prevent="simpan">
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Nama Kegiatan
                    <input v-model="form.nama" type="text" required maxlength="255" :class="inputClass" placeholder="Mis. Kajian Kitab Bulanan">
                </label>
                <p v-if="form.errors.nama" class="text-xs font-medium text-rose-600">{{ form.errors.nama }}</p>

                <div class="grid grid-cols-2 gap-3">
                    <label class="grid gap-1.5 text-sm font-bold text-slate-700">Jenis
                        <select v-model="form.jenis" :class="inputClass"><option v-for="j in opsiJenis" :key="j" :value="j" class="capitalize">{{ j }}</option></select>
                    </label>
                    <label class="grid gap-1.5 text-sm font-bold text-slate-700">Status
                        <select v-model="form.status" :class="inputClass"><option v-for="s in opsiStatus" :key="s" :value="s" class="capitalize">{{ s }}</option></select>
                    </label>
                </div>

                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Mulai
                    <input v-model="form.mulai" type="datetime-local" required :class="inputClass">
                </label>
                <p v-if="form.errors.mulai" class="text-xs font-medium text-rose-600">{{ form.errors.mulai }}</p>

                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Selesai (opsional)
                    <input v-model="form.selesai" type="datetime-local" :class="inputClass">
                </label>
                <p v-if="form.errors.selesai" class="text-xs font-medium text-rose-600">{{ form.errors.selesai }}</p>

                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Lokasi (opsional)
                    <input v-model="form.lokasi" type="text" maxlength="255" :class="inputClass" placeholder="Aula / Masjid / Lapangan">
                </label>

                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Penanggung Jawab (opsional)
                    <select v-model="form.penanggung_jawab_id" :class="inputClass">
                        <option value="">— pilih —</option>
                        <option v-for="p in pjOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </label>

                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Deskripsi (opsional)
                    <textarea v-model="form.deskripsi" rows="2" maxlength="2000" :class="inputClass"></textarea>
                </label>

                <UiButton type="submit" variant="primary" size="lg" block :loading="form.processing" :disabled="form.processing">Simpan Kegiatan</UiButton>
            </form>
        </BottomSheet>
    </AppShell>
</template>
