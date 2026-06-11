<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    santri: { type: Array, default: () => [] },
    periode: { type: Array, default: () => [] },
    istilah: { type: Object, default: () => ({}) },
})

function freshForm() {
    return {
        santri_id: '',
        juz_diuji: '',
        hasil: 'lulus',
        nilai: null,
        tanggal: new Date().toISOString().slice(0, 10),
        periode_akademik_id: null,
        // Idempoten: satu UUID per entri — retry mengirim ref sama, server dedup.
        client_ref: crypto.randomUUID(),
    }
}

const form = ref(freshForm())
const saving = ref(false)
const successMessage = ref(null)
const errorMessage = ref(null)

function submit(attempt = 0) {
    saving.value = true
    errorMessage.value = null

    router.post('/guru/munaqasyah', { ...form.value }, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'Hasil munaqasyah tercatat.'
            form.value = freshForm()
        },
        onError: () => {
            errorMessage.value = 'Periksa kembali isian munaqasyah.'
        },
        onFinish: (visit) => {
            saving.value = false
            if (!visit.completed && attempt < 1) {
                window.setTimeout(() => submit(attempt + 1), 1500)
            } else if (!visit.completed && attempt >= 1) {
                errorMessage.value = 'Gagal terkirim (jaringan). Coba lagi saat sinyal membaik.'
            }
        },
    })
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Munaqasyah</title>
        </Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Tahfidz Lanjutan</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Catat Munaqasyah</h2>
        </section>

        <section v-if="santri.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada {{ istilah.santri || 'santri' }} binaan</p>
            <p class="mt-2 text-sm text-slate-500">Tambahkan anggota {{ istilah.halaqah || 'halaqah' }} dari panel admin.</p>
        </section>

        <form v-else class="space-y-4 rounded-[1.5rem] bg-white px-5 py-5 shadow" @submit.prevent="submit()">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="mnq-santri">Santri</label>
                <select id="mnq-santri" v-model="form.santri_id" required class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    <option value="" disabled>Pilih santri…</option>
                    <option v-for="s in santri" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="mnq-juz">Juz diuji</label>
                <input id="mnq-juz" v-model="form.juz_diuji" type="text" required maxlength="255" placeholder="cth. Juz 1-10" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            </div>

            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Hasil</span>
                <div class="mt-1 grid grid-cols-2 gap-2">
                    <button type="button" class="rounded-xl px-3 py-2 text-sm font-semibold" :class="form.hasil === 'lulus' ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600'" @click="form.hasil = 'lulus'">
                        Lulus
                    </button>
                    <button type="button" class="rounded-xl px-3 py-2 text-sm font-semibold" :class="form.hasil === 'mengulang' ? 'bg-rose-700 text-white' : 'bg-slate-100 text-slate-600'" @click="form.hasil = 'mengulang'">
                        Mengulang
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="mnq-nilai">Nilai (0–100)</label>
                    <input id="mnq-nilai" v-model.number="form.nilai" type="number" min="0" max="100" step="0.01" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="mnq-tanggal">Tanggal</label>
                    <input id="mnq-tanggal" v-model="form.tanggal" type="date" required class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="mnq-periode">Periode akademik</label>
                <select id="mnq-periode" v-model="form.periode_akademik_id" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    <option :value="null">— Tanpa periode —</option>
                    <option v-for="p in periode" :key="p.id" :value="p.id">{{ p.nama }}</option>
                </select>
            </div>

            <p v-if="errorMessage" class="rounded-2xl bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700">{{ errorMessage }}</p>
            <p v-if="successMessage" class="rounded-2xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">{{ successMessage }}</p>

            <button type="submit" class="w-full rounded-full bg-emerald-700 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-white disabled:opacity-60" :disabled="saving">
                {{ saving ? 'Menyimpan…' : 'Catat Munaqasyah' }}
            </button>
        </form>
    </div>
</template>
