<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    santri: { type: Array, default: () => [] },
    istilah: { type: Object, default: () => ({}) },
})

function freshForm() {
    return {
        santri_id: '',
        rentang: '',
        nilai_kelancaran: null,
        nilai_tajwid: null,
        catatan: '',
        tanggal: new Date().toISOString().slice(0, 10),
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

    router.post('/guru/simaan', { ...form.value }, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = "Sima'an tercatat."
            form.value = freshForm()
        },
        onError: () => {
            errorMessage.value = "Periksa kembali isian sima'an."
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
            <title>Sima'an</title>
        </Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Tahfidz Lanjutan</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Catat Sima'an</h2>
        </section>

        <section v-if="santri.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada {{ istilah.santri || 'santri' }} binaan</p>
            <p class="mt-2 text-sm text-slate-500">Tambahkan anggota {{ istilah.halaqah || 'halaqah' }} dari panel admin.</p>
        </section>

        <form v-else class="space-y-4 rounded-[1.5rem] bg-white px-5 py-5 shadow" @submit.prevent="submit()">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="simaan-santri">Santri</label>
                <select id="simaan-santri" v-model="form.santri_id" required class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    <option value="" disabled>Pilih santri…</option>
                    <option v-for="s in santri" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="simaan-rentang">Rentang</label>
                <input id="simaan-rentang" v-model="form.rentang" type="text" required maxlength="255" placeholder="cth. Juz 1-5 / Al-Baqarah 1-141" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="simaan-kelancaran">Kelancaran (0–100)</label>
                    <input id="simaan-kelancaran" v-model.number="form.nilai_kelancaran" type="number" min="0" max="100" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="simaan-tajwid">Tajwid (0–100)</label>
                    <input id="simaan-tajwid" v-model.number="form.nilai_tajwid" type="number" min="0" max="100" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="simaan-tanggal">Tanggal</label>
                <input id="simaan-tanggal" v-model="form.tanggal" type="date" required class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="simaan-catatan">Catatan</label>
                <textarea id="simaan-catatan" v-model="form.catatan" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
            </div>

            <p v-if="errorMessage" class="rounded-2xl bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700">{{ errorMessage }}</p>
            <p v-if="successMessage" class="rounded-2xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">{{ successMessage }}</p>

            <button type="submit" class="w-full rounded-full bg-emerald-700 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-white disabled:opacity-60" :disabled="saving">
                {{ saving ? 'Menyimpan…' : "Catat Sima'an" }}
            </button>
        </form>
    </div>
</template>
