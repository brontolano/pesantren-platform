<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

defineOptions({ layout: PortalGuruLayout })

defineProps({
    sesi: { type: Array, default: () => [] },
})

const tarikUntuk = ref(null) // sesi id yang sedang pilih komponen
const komponenDipilih = ref(null)

function tarikNilai(item) {
    if (!komponenDipilih.value) return
    router.post(`/guru/ujian/${item.id}/tarik-nilai`, { komponen_id: komponenDipilih.value }, {
        onFinish: () => { tarikUntuk.value = null; komponenDipilih.value = null },
    })
}
</script>

<template>
    <div class="space-y-5">
        <Head><title>Ujian Online</title></Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Ujian Semester Online</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Sesi Ujian Saya</h2>
            <p class="text-sm text-slate-500">Pelajaran yang Anda ampu. Jadwal sesi dibuat dari panel admin.</p>
        </section>

        <section class="flex gap-2">
            <Link href="/guru/ujian/koreksi" class="rounded-2xl bg-amber-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white shadow-sm">
                Koreksi Essay →
            </Link>
        </section>

        <section v-if="sesi.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada sesi ujian</p>
            <p class="mt-2 text-sm text-slate-500">Buat paket soal & sesi dari panel admin (menu Akademik).</p>
        </section>

        <section v-else class="space-y-3">
            <div v-for="item in sesi" :key="item.id" class="rounded-2xl bg-white px-4 py-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ item.nama }}</p>
                        <p class="text-xs text-slate-400">{{ item.pelajaran }} · {{ item.kelas }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ item.mulai_at }} → {{ item.selesai_at }}</p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase"
                        :class="item.berlangsung ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                    >{{ item.berlangsung ? 'Berlangsung' : 'Tutup' }}</span>
                </div>

                <p class="mt-2 text-xs text-slate-500">
                    {{ item.jumlah_attempt }} attempt · {{ item.jumlah_selesai }} selesai
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <Link :href="`/guru/ujian/${item.id}/pengawas`" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white">
                        Mode Pengawas
                    </Link>
                    <button
                        v-if="tarikUntuk !== item.id"
                        type="button"
                        class="rounded-xl border border-emerald-600 px-3 py-2 text-xs font-semibold text-emerald-700"
                        @click="tarikUntuk = item.id; komponenDipilih = null"
                    >Tarik ke Nilai</button>
                    <template v-else>
                        <select v-model="komponenDipilih" class="rounded-xl border-slate-300 py-2 text-xs">
                            <option :value="null" disabled>Pilih komponen…</option>
                            <option v-for="k in item.komponen" :key="k.id" :value="k.id">{{ k.nama }}</option>
                        </select>
                        <button type="button" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white" @click="tarikNilai(item)">Tarik</button>
                        <button type="button" class="px-2 py-2 text-xs text-slate-400" @click="tarikUntuk = null">Batal</button>
                    </template>
                </div>
            </div>
        </section>
    </div>
</template>
