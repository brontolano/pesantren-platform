<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import { UiCard, UiButton, EmptyState } from '@/Components/Ui'

defineOptions({
    layout: PortalWaliLayout,
})

const props = defineProps({
    child: {
        type: Object,
        required: true,
    },
    placementHistory: {
        type: Array,
        default: () => [],
    },
})

const flashSuccess = computed(() => usePage().props?.flash?.success || '')
const pinForm = useForm({ pin: '' })

function simpanPin() {
    pinForm.post(`/wali/santri/${props.child.id}/atur-pin`, {
        preserveScroll: true,
        onSuccess: () => pinForm.reset('pin'),
    })
}

const detail = computed(() => [
    ['Status', props.child.status],
    ['Tanggal lahir', props.child.tanggalLahir],
    ['Tempat lahir', props.child.tempatLahir],
    ['Jenis kelamin', props.child.jenisKelamin],
    ['Agama', props.child.agama],
    ['Asal sekolah', props.child.asalSekolah],
    ['Alamat', props.child.alamat],
    ['Angkatan', props.child.tahunAngkatan],
    ['Ayah', props.child.namaAyah],
    ['Ibu', props.child.namaIbu],
    ['No HP wali', props.child.noHpWali],
    ['Email wali', props.child.emailWali],
])
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Profil Santri</title>
        </Head>

        <!-- HERO -->
        <UiCard variant="hero-dark" padding="lg">
            <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">Profil Santri</p>
            <h2 class="mt-1 text-2xl font-extrabold leading-tight tracking-tight">{{ child.nama }}</h2>
            <p class="mt-2 text-sm leading-6 text-white/80">
                NIS {{ child.nis }} · {{ child.kelas?.nama || 'Belum ada kelas' }}
            </p>
        </UiCard>

        <!-- DETAIL -->
        <UiCard padding="none">
            <div class="flex items-center gap-2.5 px-5 pt-5">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-grad" />
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Data Santri</p>
            </div>
            <dl class="mt-2 divide-y divide-slate-100">
                <div v-for="[label, value] in detail" :key="label" class="flex items-start justify-between gap-3 px-5 py-3">
                    <dt class="text-sm text-slate-500">{{ label }}</dt>
                    <dd class="max-w-[60%] text-right text-sm font-semibold text-slate-900">{{ value || '-' }}</dd>
                </div>
            </dl>
        </UiCard>

        <!-- ATUR PIN -->
        <UiCard padding="md" class="ring-emerald-200/70">
            <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Login Aplikasi Santri</p>
            <h3 class="mt-1 text-lg font-extrabold tracking-tight text-emerald-950">Atur PIN anak</h3>
            <p class="mt-1 text-sm leading-6 text-slate-600">
                Santri masuk App Santri dengan <b>NIS {{ child.nis }}</b> + PIN 6 angka.
                Buat PIN yang mudah diingat anak.
            </p>

            <p v-if="flashSuccess" class="mt-3 rounded-xl bg-emerald-600/10 px-3 py-2 text-sm font-medium text-emerald-800">
                {{ flashSuccess }}
            </p>

            <form class="mt-3 flex flex-wrap items-start gap-3" @submit.prevent="simpanPin">
                <div>
                    <input
                        v-model="pinForm.pin"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        pattern="\d{6}"
                        placeholder="6 angka"
                        class="w-32 rounded-2xl border border-emerald-300 px-4 py-3 text-center text-lg tracking-[0.4em] tabular-nums outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                    >
                    <p v-if="pinForm.errors.pin" class="mt-1 text-xs font-medium text-rose-600">{{ pinForm.errors.pin }}</p>
                </div>
                <UiButton
                    type="submit"
                    variant="primary"
                    size="lg"
                    :disabled="pinForm.processing || !/^\d{6}$/.test(pinForm.pin)"
                    :loading="pinForm.processing"
                >
                    Simpan PIN
                </UiButton>
            </form>
        </UiCard>

        <!-- RIWAYAT KELAS -->
        <section class="space-y-3">
            <div class="flex items-center gap-2.5">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-grad" />
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Riwayat Kelas</p>
            </div>

            <div v-if="placementHistory.length" class="grid gap-3">
                <UiCard
                    v-for="item in placementHistory"
                    :key="`${item.kelas}-${item.tanggalPenempatan}`"
                    padding="md"
                >
                    <p class="font-bold text-slate-900">{{ item.kelas || 'Belum ada kelas' }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ item.tahunAkademik || '-' }} · {{ item.semester || '-' }}</p>
                    <p class="mt-0.5 text-sm text-slate-500">{{ item.tanggalPenempatan || '-' }}</p>
                </UiCard>
            </div>
            <EmptyState v-else icon="calendar" title="Belum ada riwayat penempatan" message="Riwayat kelas santri akan tampil di sini." />
        </section>

        <UiButton
            variant="secondary"
            size="lg"
            block
            as="link"
            :href="`/wali/santri/${child.id}/akademik`"
            icon-right="arrow-up-right"
        >
            Lanjut ke akademik
        </UiButton>
    </div>
</template>
