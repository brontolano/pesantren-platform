<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    santri: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    moderasiPerhatian: { type: Boolean, default: true },
    riwayat: { type: Array, default: () => [] },
    istilah: { type: Object, default: () => ({}) },
})

const form = useForm({
    santri_id: '',
    jenis: 'apresiasi',
    isi: '',
    client_ref: crypto.randomUUID(),
})

const templateUntukJenis = computed(() =>
    props.templates.filter((t) => t.jenis === form.jenis),
)

function pakaiTemplate(t) {
    form.jenis = t.jenis
    form.isi = t.isi
}

function kirim() {
    form.post('/guru/pesan', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('isi')
            form.client_ref = crypto.randomUUID()
        },
    })
}

const labelJenis = {
    apresiasi: 'Apresiasi',
    perhatian: 'Perhatian',
    info: 'Info',
}

const warnaJenis = {
    apresiasi: 'bg-emerald-50 text-emerald-700',
    perhatian: 'bg-amber-50 text-amber-700',
    info: 'bg-sky-50 text-sky-700',
}

const warnaStatus = {
    menunggu_moderasi: 'bg-amber-50 text-amber-700',
    terkirim: 'bg-emerald-50 text-emerald-700',
    ditolak: 'bg-rose-50 text-rose-700',
    draft: 'bg-slate-100 text-slate-600',
}

const labelStatus = {
    menunggu_moderasi: 'Menunggu Moderasi',
    terkirim: 'Terkirim',
    ditolak: 'Ditolak',
    draft: 'Draf',
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Pesan Wali</title>
        </Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Pesan Wali</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Kirim Pesan ke Wali {{ istilah.santri || 'Santri' }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                Hanya {{ (istilah.santri || 'santri').toLowerCase() }} binaan Anda yang bisa dipilih.
                <span v-if="moderasiPerhatian">Pesan jenis <strong>perhatian</strong> ditinjau pengurus dulu sebelum terkirim.</span>
            </p>
        </section>

        <!-- Form kirim -->
        <form class="space-y-4 rounded-[1.5rem] bg-white px-5 py-5 shadow" @submit.prevent="kirim">
            <div>
                <label class="text-sm font-medium text-slate-700">{{ istilah.santri || 'Santri' }}</label>
                <select v-model="form.santri_id" required class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    <option value="" disabled>Pilih…</option>
                    <option v-for="s in santri" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis || '—' }})</option>
                </select>
                <p v-if="form.errors.santri_id" class="mt-1 text-xs text-rose-600">{{ form.errors.santri_id }}</p>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Jenis Pesan</label>
                <div class="mt-2 flex gap-2">
                    <button
                        v-for="(label, jenis) in labelJenis"
                        :key="jenis"
                        type="button"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                        :class="form.jenis === jenis ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'"
                        @click="form.jenis = jenis"
                    >
                        {{ label }}
                    </button>
                </div>
            </div>

            <div v-if="templateUntukJenis.length > 0">
                <label class="text-sm font-medium text-slate-700">Template Siap-Pakai</label>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-for="t in templateUntukJenis"
                        :key="t.label"
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50"
                        @click="pakaiTemplate(t)"
                    >
                        {{ t.label }}
                    </button>
                </div>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Isi Pesan</label>
                <textarea
                    v-model="form.isi"
                    rows="5"
                    required
                    minlength="5"
                    maxlength="2000"
                    class="mt-1 w-full rounded-xl border-slate-200 text-sm"
                    placeholder="Pilih template lalu sunting, atau tulis sendiri…"
                />
                <p v-if="form.errors.isi" class="mt-1 text-xs text-rose-600">{{ form.errors.isi }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-2xl bg-amber-600 py-3 text-sm font-semibold text-white shadow disabled:opacity-50"
            >
                {{ form.jenis === 'perhatian' && moderasiPerhatian ? 'Ajukan (lewat moderasi)' : 'Kirim ke Wali' }}
            </button>
        </form>

        <!-- Riwayat -->
        <section class="space-y-3">
            <h3 class="text-sm font-semibold text-slate-700">Riwayat Pesan Saya</h3>
            <p v-if="riwayat.length === 0" class="rounded-[1.5rem] bg-white px-5 py-6 text-center text-sm text-slate-400 shadow">
                Belum ada pesan.
            </p>
            <div v-for="p in riwayat" :key="p.id" class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-base font-semibold text-slate-900">{{ p.santri }}</p>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="warnaStatus[p.status]">
                        {{ labelStatus[p.status] || p.status }}
                    </span>
                </div>
                <div class="mt-1 flex items-center gap-2 text-xs text-slate-500">
                    <span class="rounded-full px-2 py-0.5" :class="warnaJenis[p.jenis]">{{ labelJenis[p.jenis] || p.jenis }}</span>
                    <span>{{ p.sent_at || p.created_at }}</span>
                    <span v-if="p.dikirim_via" class="text-slate-400">via {{ p.dikirim_via }}</span>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ p.isi }}</p>
                <p v-if="p.alasan_tolak" class="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    Ditolak moderator: {{ p.alasan_tolak }}
                </p>
            </div>
        </section>
    </div>
</template>
