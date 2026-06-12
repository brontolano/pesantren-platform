<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import UiIcon from '@/Components/Ui/UiIcon.vue'

defineOptions({ layout: PortalWaliLayout })

const props = defineProps({
    children: { type: Array, default: () => [] },
    submissions: { type: Array, default: () => [] },
    jengukSubmissions: { type: Array, default: () => [] },
    jadwalJenguk: { type: String, default: '' },
})

const showForm = ref(false)

const form = useForm({
    santri_id: props.children[0]?.id ?? '',
    jenis_izin: 'sakit',
    tanggal_mulai: '',
    tanggal_selesai: '',
    alasan: '',
    catatan_wali: '',
})

function submit() {
    form.post('/wali/izin', { onSuccess: () => { showForm.value = false } })
}

function statusBadge(status) {
    const s = String(status || '').toLowerCase()
    if (['disetujui', 'approved'].includes(s)) return { label: 'Disetujui', bg: 'bg-emerald-50', text: 'text-emerald-700', iconBg: 'bg-emerald-50', iconText: 'text-emerald-700' }
    if (['ditolak', 'rejected'].includes(s)) return { label: 'Ditolak', bg: 'bg-rose-50', text: 'text-rose-700', iconBg: 'bg-rose-50', iconText: 'text-rose-700' }
    if (['selesai', 'completed'].includes(s)) return { label: 'Selesai', bg: 'bg-slate-100', text: 'text-slate-600', iconBg: 'bg-slate-100', iconText: 'text-slate-600' }
    return { label: 'Diajukan', bg: 'bg-amber-50', text: 'text-amber-700', iconBg: 'bg-amber-50', iconText: 'text-amber-700' }
}

function statusIcon(status) {
    const s = String(status || '').toLowerCase()
    if (['disetujui', 'approved'].includes(s)) return 'check-circle'
    if (['selesai', 'completed'].includes(s)) return 'document'
    return 'clock'
}

const allSubmissions = [...(props.submissions || []).map(s => ({ ...s, tipe: 'izin' })), ...(props.jengukSubmissions || []).map(s => ({ ...s, tipe: 'jenguk' }))]
</script>

<template>
    <div class="space-y-4">
        <Head><title>Izin &amp; Jenguk</title></Head>

        <div>
            <p class="font-jost text-[11px] font-semibold uppercase tracking-[0.16em] text-[#a07c2c]">Pengajuan ke pesantren</p>
            <h1 class="mt-1 font-jost text-[26px] font-bold tracking-tight text-[#111]">Izin &amp; Jenguk</h1>
        </div>

        <!-- Two-card hub -->
        <div class="flex gap-[11px]">
            <button
                class="flex flex-1 flex-col rounded-[24px] bg-gradient-to-br from-[#0b8f63] to-emerald-700 p-[18px] text-left text-white shadow-[0_16px_44px_-24px_rgba(17,17,17,0.4)]"
                @click="showForm = !showForm"
            >
                <span class="flex h-[42px] w-[42px] items-center justify-center rounded-[14px] bg-white/16 ring-1 ring-inset ring-white/25">
                    <UiIcon name="document" class="h-5 w-5" />
                </span>
                <span class="mt-3 font-jost text-[15.5px] font-bold">Ajukan Izin</span>
                <span class="mt-[3px] text-xs leading-[1.45] text-white/75">Sakit, pulang, atau keperluan khusus</span>
            </button>
            <Link
                href="/wali/jenguk"
                class="flex flex-1 flex-col rounded-[24px] bg-white p-[18px] text-left shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]"
            >
                <span class="flex h-[42px] w-[42px] items-center justify-center rounded-[14px] bg-[#f7efdc] text-[#a07c2c]">
                    <UiIcon name="calendar" class="h-5 w-5" />
                </span>
                <span class="mt-3 font-jost text-[15.5px] font-bold text-[#111]">Ajukan Jenguk</span>
                <span class="mt-[3px] text-xs leading-[1.45] text-slate-500">Pesan slot kunjungan keluarga</span>
            </Link>
        </div>

        <!-- Info banner -->
        <div v-if="jadwalJenguk" class="flex items-center gap-[11px] rounded-[18px] bg-[#f7efdc] px-[17px] py-3 shadow-[inset_0_0_0_1px_#e7d3a1]">
            <UiIcon name="info" class="h-[17px] w-[17px] shrink-0 text-[#a07c2c]" />
            <p class="text-xs leading-relaxed text-[#7a5e1e]" v-html="jadwalJenguk" />
        </div>
        <div v-else class="flex items-center gap-[11px] rounded-[18px] bg-[#f7efdc] px-[17px] py-3 shadow-[inset_0_0_0_1px_#e7d3a1]">
            <UiIcon name="info" class="h-[17px] w-[17px] shrink-0 text-[#a07c2c]" />
            <p class="text-xs leading-relaxed text-[#7a5e1e]">Jadwal jenguk akan ditampilkan sesuai kebijakan pesantren.</p>
        </div>

        <!-- Inline form (toggle) -->
        <form
            v-if="showForm"
            class="space-y-3 rounded-[22px] bg-white px-5 py-5 shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]"
            @submit.prevent="submit"
        >
            <p class="font-jost text-[15px] font-semibold text-[#111]">Formulir Izin</p>

            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                Pilih santri
                <select v-model="form.santri_id" class="rounded-2xl border border-[#efe7da] bg-[#fffaf3] px-4 py-3 text-sm">
                    <option v-for="child in children" :key="child.id" :value="child.id">{{ child.nama }}</option>
                </select>
            </label>

            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                Jenis izin
                <select v-model="form.jenis_izin" class="rounded-2xl border border-[#efe7da] bg-[#fffaf3] px-4 py-3 text-sm">
                    <option value="sakit">Sakit</option>
                    <option value="pulang">Pulang</option>
                    <option value="khusus">Khusus</option>
                </select>
            </label>

            <div class="grid grid-cols-2 gap-3">
                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                    Tanggal mulai
                    <input v-model="form.tanggal_mulai" type="date" class="rounded-2xl border border-[#efe7da] bg-[#fffaf3] px-4 py-3 text-sm">
                </label>
                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                    Tanggal selesai
                    <input v-model="form.tanggal_selesai" type="date" class="rounded-2xl border border-[#efe7da] bg-[#fffaf3] px-4 py-3 text-sm">
                </label>
            </div>

            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                Alasan
                <textarea v-model="form.alasan" rows="3" class="rounded-2xl border border-[#efe7da] bg-[#fffaf3] px-4 py-3 text-sm" />
            </label>

            <button
                type="submit"
                class="w-full rounded-full bg-emerald-600 py-[14px] font-jost text-[15px] font-semibold text-white shadow-[0_8px_20px_-12px_rgba(5,150,105,0.45)]"
                :disabled="form.processing"
            >
                Kirim Pengajuan
            </button>
        </form>

        <!-- Riwayat pengajuan -->
        <div class="space-y-[11px]">
            <h3 class="font-jost text-base font-semibold text-[#111]">Riwayat pengajuan</h3>

            <template v-if="submissions.length">
                <div
                    v-for="sub in submissions"
                    :key="sub.id"
                    class="flex items-center gap-[13px] rounded-[22px] bg-white px-[17px] py-[15px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]"
                    :class="statusBadge(sub.status).text === 'text-slate-600' ? 'opacity-75' : ''"
                >
                    <span class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-[14px]" :class="[statusBadge(sub.status).iconBg, statusBadge(sub.status).iconText]">
                        <UiIcon :name="statusIcon(sub.status)" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-800">{{ sub.jenisIzin || 'Izin' }} · {{ sub.santri }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ sub.tanggalMulai }}–{{ sub.tanggalSelesai }} · {{ sub.alasan }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-[5px]">
                        <span class="rounded-full px-[11px] py-1 font-jost text-[10.5px] font-semibold" :class="[statusBadge(sub.status).bg, statusBadge(sub.status).text]">
                            {{ statusBadge(sub.status).label }}
                        </span>
                        <UiIcon name="chevron-right" class="h-[14px] w-[14px] text-slate-400" />
                    </div>
                </div>
            </template>

            <div v-else class="rounded-[22px] bg-white px-5 py-6 text-center shadow-[inset_0_0_0_1px_#efe7da]">
                <p class="text-sm text-slate-500">Belum ada pengajuan izin.</p>
            </div>
        </div>
    </div>
</template>
