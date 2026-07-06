<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, IconCircle, UiButton, EmptyState, BottomSheet } from '@/Shared'

const props = defineProps({
    daftar: { type: Array, default: () => [] },
    bolehTulis: { type: Boolean, default: false },
    opsiKategori: { type: Array, default: () => [] },
})

const nav = [
    { key: 'beranda', label: 'Beranda', href: '/staf', active: false },
    { key: 'tazir', label: "Ta'zir", href: '/kedisiplinan-app/jenis-tazir', active: true },
]

const buka = ref(false)
const form = useForm({ nama: '', kategori: 'ringan', poin: 5, sanksi_default: '' })
function simpan() {
    form.post('/kedisiplinan-app/jenis-tazir', { preserveScroll: true, onSuccess: () => { buka.value = false; form.reset() } })
}

const kategoriClass = {
    ringan: 'bg-sky-100 text-sky-700',
    sedang: 'bg-amber-100 text-amber-700',
    berat: 'bg-rose-100 text-rose-700',
    prestasi: 'bg-emerald-100 text-emerald-700',
}
const inputClass = 'w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-[var(--brand-primary)] focus:ring-2 focus:ring-[var(--brand-primary)]/20'
</script>

<template>
    <AppShell :nav-items="nav" brand-name="Kedisiplinan">
        <Head><title>Jenis Ta'zir</title></Head>
        <div class="space-y-5">
            <PageHeader kicker="Kedisiplinan" title="Jenis Ta'zir" subtitle="Master pelanggaran & prestasi (poin negatif)." />

            <UiButton v-if="bolehTulis" variant="primary" size="lg" block @click="buka = true">+ Tambah Jenis</UiButton>

            <EmptyState v-if="daftar.length === 0" icon="info" title="Belum ada jenis ta'zir" message="Master jenis pelanggaran akan tampil di sini." />

            <SectionCard v-else title="Daftar Jenis" :subtitle="`${daftar.length} jenis`" flush>
                <div class="divide-y divide-slate-100">
                    <div v-for="j in daftar" :key="j.id" class="flex items-start gap-3 px-5 py-4">
                        <IconCircle icon="info" :tone="j.kategori === 'prestasi' ? 'emerald' : 'rose'" variant="tint" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-slate-900">{{ j.nama }}</p>
                            <p v-if="j.sanksi" class="mt-0.5 text-xs text-slate-500">{{ j.sanksi }}</p>
                            <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold capitalize" :class="kategoriClass[j.kategori] || 'bg-slate-100 text-slate-600'">{{ j.kategori }}</span>
                        </div>
                        <span class="shrink-0 text-lg font-extrabold tabular-nums" :class="j.poin < 0 ? 'text-emerald-600' : 'text-rose-600'">{{ j.poin }}</span>
                    </div>
                </div>
            </SectionCard>
        </div>

        <BottomSheet v-model="buka" title="Tambah Jenis Ta'zir">
            <form class="space-y-3.5" @submit.prevent="simpan">
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Nama
                    <input v-model="form.nama" type="text" required maxlength="255" :class="inputClass" placeholder="Mis. Terlambat sholat berjamaah">
                </label>
                <p v-if="form.errors.nama" class="text-xs font-medium text-rose-600">{{ form.errors.nama }}</p>

                <div class="grid grid-cols-2 gap-3">
                    <label class="grid gap-1.5 text-sm font-bold text-slate-700">Kategori
                        <select v-model="form.kategori" :class="inputClass">
                            <option v-for="k in opsiKategori" :key="k" :value="k" class="capitalize">{{ k }}</option>
                        </select>
                    </label>
                    <label class="grid gap-1.5 text-sm font-bold text-slate-700">Poin
                        <input v-model.number="form.poin" type="number" required :class="inputClass">
                    </label>
                </div>
                <p v-if="form.errors.poin" class="text-xs font-medium text-rose-600">{{ form.errors.poin }}</p>
                <p class="text-xs text-slate-500">Prestasi berpoin negatif (mis. -10); pelanggaran berpoin positif.</p>

                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Sanksi Default (opsional)
                    <textarea v-model="form.sanksi_default" rows="2" maxlength="500" :class="inputClass" placeholder="Mis. Panggilan wali + pembinaan"></textarea>
                </label>

                <UiButton type="submit" variant="primary" size="lg" block :loading="form.processing" :disabled="form.processing">Simpan</UiButton>
            </form>
        </BottomSheet>
    </AppShell>
</template>
