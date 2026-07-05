<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, UiButton, StatusBadge, EmptyState, UiIcon } from '@/Shared'

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

        <PageHeader
            kicker="Ujian Semester Online"
            title="Sesi Ujian Saya"
            subtitle="Pelajaran yang Anda ampu. Jadwal sesi dibuat dari panel admin."
        >
            <template #right>
                <UiButton variant="primary" size="sm" href="/guru/ujian/koreksi" iconRight="arrow-up-right">
                    Koreksi Essay
                </UiButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="sesi.length === 0"
            icon="document"
            title="Belum ada sesi ujian"
            message="Buat paket soal & sesi dari panel admin (menu Akademik)."
        />

        <SectionCard v-else flush>
            <div class="divide-y divide-slate-100">
                <div v-for="item in sesi" :key="item.id" class="px-4 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ item.nama }}</p>
                            <p class="text-xs text-slate-400">{{ item.pelajaran }} · {{ item.kelas }}</p>
                            <p class="mt-1 flex items-center gap-1 text-xs text-slate-500">
                                <UiIcon name="clock" size="14" />
                                {{ item.mulai_at }} → {{ item.selesai_at }}
                            </p>
                        </div>
                        <StatusBadge
                            :status="item.berlangsung ? 'aktif' : 'batal'"
                            :label="item.berlangsung ? 'Berlangsung' : 'Tutup'"
                            size="sm"
                        />
                    </div>

                    <p class="mt-2 flex items-center gap-1 text-xs text-slate-500">
                        <UiIcon name="user" size="14" />
                        {{ item.jumlah_attempt }} attempt · {{ item.jumlah_selesai }} selesai
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <UiButton variant="secondary" size="sm" :href="`/guru/ujian/${item.id}/pengawas`" iconLeft="chart">
                            Mode Pengawas
                        </UiButton>
                        <UiButton
                            v-if="tarikUntuk !== item.id"
                            variant="ghost"
                            size="sm"
                            type="button"
                            iconLeft="arrow-down-left"
                            @click="tarikUntuk = item.id; komponenDipilih = null"
                        >Tarik ke Nilai</UiButton>
                        <template v-else>
                            <select
                                v-model="komponenDipilih"
                                class="rounded-xl border-slate-300 py-2 text-xs font-medium text-slate-700 shadow-sm focus:border-violet-500 focus:ring-violet-500"
                            >
                                <option :value="null" disabled>Pilih komponen…</option>
                                <option v-for="k in item.komponen" :key="k.id" :value="k.id">{{ k.nama }}</option>
                            </select>
                            <UiButton variant="primary" size="sm" type="button" @click="tarikNilai(item)">Tarik</UiButton>
                            <UiButton variant="ghost" size="sm" type="button" @click="tarikUntuk = null">Batal</UiButton>
                        </template>
                    </div>
                </div>
            </div>
        </SectionCard>
    </div>
</template>
