<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    AppShell, PageHeader, SectionCard, ListRow, StatCard, StatusBadge,
    UiCard, UiButton, UiField, UiInput, BottomSheet, IconCircle,
} from '@/Shared'

const props = defineProps({
    calon: { type: Object, default: () => ({}) },
    opsiKeputusan: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const navIcons = {
    beranda: 'chart',
    pendaftar: 'user',
    verifikasi: 'check-circle',
    hasil: 'document',
}

const sheetOpen = ref(false)
const pilihan = ref(null)
const catatan = ref('')
const mengirim = ref(false)

const pilihanMeta = computed(() =>
    props.opsiKeputusan.find((o) => o.value === pilihan.value) ?? null,
)

function buka(opsi) {
    pilihan.value = opsi.value
    catatan.value = ''
    sheetOpen.value = true
}

function konfirmasi() {
    mengirim.value = true
    router.post(`/ppdb-panitia/keputusan/${props.calon.id}`, {
        status_pendaftaran: pilihan.value,
        catatan_keputusan: catatan.value || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            mengirim.value = false
            sheetOpen.value = false
        },
    })
}

// Petakan value keputusan -> status badge yang dikenal StatusBadge (berdasarkan
// status FINAL server, bukan status lokal-optimistic).
const badgeStatus = computed(() => {
    if (props.calon.statusSaatIni === 'lulus') return 'terverifikasi'
    if (props.calon.statusSaatIni === 'cadangan') return 'menunggu'
    if (props.calon.statusSaatIni === 'ditolak') return 'ditolak'
    return null
})
</script>

<template>
    <Head title="Keputusan Kelulusan — PPDB Panitia" />

    <AppShell :nav-items="navigation" :nav-icons="navIcons" brand-name="PPDB Panitia">
        <div class="space-y-5">
            <PageHeader
                kicker="Keputusan"
                title="Keputusan Kelulusan"
                subtitle="Tetapkan status akhir calon santri berdasarkan kelengkapan & hasil seleksi."
            />

            <!-- Identitas + rekap -->
            <UiCard>
                <p class="text-base font-bold tracking-tight text-slate-900">{{ calon.nama }}</p>
                <p class="mt-0.5 text-sm text-slate-500">{{ calon.no }} · {{ calon.program }} · Gel. {{ calon.gelombang }}</p>
                <p class="mt-0.5 text-sm text-slate-500">{{ calon.asal }}</p>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <StatusBadge
                        :status="calon.dokumenLengkap ? 'terverifikasi' : 'menunggu'"
                        :label="calon.dokumenLengkap ? 'Berkas lengkap' : 'Berkas belum lengkap'"
                        size="sm"
                    />
                    <StatusBadge v-if="badgeStatus" :status="badgeStatus" :label="`Diputus: ${pilihanMeta?.label}`" size="sm" />
                </div>
            </UiCard>

            <!-- Hasil seleksi ringkas -->
            <SectionCard title="Nilai Seleksi">
                <div class="grid grid-cols-2 gap-3">
                    <StatCard
                        v-for="(u, i) in calon.ujian"
                        :key="i"
                        tone="blue"
                        :label="u.label"
                        :value="String(u.nilai)"
                        compact
                    />
                    <StatCard tone="emerald" label="Rata-rata" :value="String(calon.rataRata)" hint="dari 100" compact />
                </div>
            </SectionCard>

            <!-- Pilihan keputusan -->
            <SectionCard kicker="Tetapkan" title="Pilih Keputusan">
                <div class="grid grid-cols-3 gap-2.5">
                    <button
                        v-for="o in opsiKeputusan"
                        :key="o.value"
                        type="button"
                        class="flex flex-col items-center gap-2 rounded-2xl bg-white p-4 text-center ring-1 ring-slate-100 shadow-soft transition-all duration-200 active:scale-[.97]"
                        :class="calon.statusSaatIni === o.value ? 'ring-2 ring-emerald-500' : ''"
                        @click="buka(o)"
                    >
                        <IconCircle
                            :icon="o.value === 'ditolak' ? 'x-circle' : o.value === 'cadangan' ? 'clock' : 'check-circle'"
                            :tone="o.tone"
                            size="md"
                        />
                        <span class="text-[13px] font-bold text-slate-800">{{ o.label }}</span>
                    </button>
                </div>
            </SectionCard>
        </div>

        <!-- BottomSheet konfirmasi keputusan -->
        <BottomSheet v-model="sheetOpen" :title="`Tetapkan: ${pilihanMeta?.label ?? ''}`">
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3">
                    <IconCircle
                        :icon="pilihan === 'ditolak' ? 'x-circle' : pilihan === 'cadangan' ? 'clock' : 'check-circle'"
                        :tone="pilihanMeta?.tone ?? 'emerald'"
                        size="md"
                    />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-900">{{ calon.nama }}</p>
                        <p class="truncate text-xs text-slate-500">{{ calon.no }} · {{ calon.program }}</p>
                    </div>
                </div>

                <UiField label="Catatan keputusan" hint="Tampil di lembar pengumuman & dapat dikirim ke wali.">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            :aria-describedby="describedby"
                            v-model="catatan"
                            type="textarea"
                            :rows="3"
                            placeholder="Mis. diterima di program Tahfizh, daftar ulang sebelum 20 Juni"
                        />
                    </template>
                </UiField>
            </div>

            <template #footer>
                <UiButton
                    block
                    :variant="pilihan === 'ditolak' ? 'danger' : 'primary'"
                    :icon-left="pilihan === 'ditolak' ? 'x-circle' : 'check'"
                    :disabled="mengirim"
                    @click="konfirmasi"
                >
                    Simpan Keputusan
                </UiButton>
            </template>
        </BottomSheet>
    </AppShell>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
</style>
