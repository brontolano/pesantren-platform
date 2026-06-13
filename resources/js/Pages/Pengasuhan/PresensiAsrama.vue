<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, reactive, computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, IconCircle, SegmentedControl, UiButton } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: '' },
    kamar: { type: String, default: '' },
    slotOptions: { type: Array, default: () => [] },
    santri: { type: Array, default: () => [] },
})

const slot = ref(props.slotOptions[0]?.value ?? 'subuh')

// State presensi lokal (optimistic) — kunci santri.id -> status.
const presensi = reactive(
    Object.fromEntries(props.santri.map((s) => [s.id, s.status])),
)

const STATUS_CYCLE = ['hadir', 'izin', 'sakit', 'alpa']
const STATUS_META = {
    hadir: { label: 'Hadir', icon: 'check-circle', tone: 'emerald', ring: 'ring-emerald-100 bg-emerald-50 text-emerald-700' },
    izin: { label: 'Izin', icon: 'document', tone: 'blue', ring: 'ring-blue-100 bg-blue-50 text-blue-700' },
    sakit: { label: 'Sakit', icon: 'heart', tone: 'amber', ring: 'ring-amber-100 bg-amber-50 text-amber-700' },
    alpa: { label: 'Alpa', icon: 'x-circle', tone: 'rose', ring: 'ring-rose-100 bg-rose-50 text-rose-700' },
}

// Fallback aman bila status di luar 4 kunci dikenal (cegah TypeError → blank).
const metaFor = (status) => STATUS_META[status] ?? STATUS_META.hadir

function cycle(id) {
    const cur = presensi[id]
    const next = STATUS_CYCLE[(STATUS_CYCLE.indexOf(cur) + 1) % STATUS_CYCLE.length]
    presensi[id] = next
}

const ringkasan = computed(() => {
    const t = { hadir: 0, izin: 0, sakit: 0, alpa: 0 }
    for (const id in presensi) t[presensi[id]]++
    return t
})

const saving = ref(false)
const tersimpan = ref(false)

// Optimistic — tidak POST ke server, hanya tampilkan konfirmasi sementara.
function simpan() {
    saving.value = true
    setTimeout(() => {
        saving.value = false
        tersimpan.value = true
        setTimeout(() => (tersimpan.value = false), 2600)
    }, 500)
}
</script>

<template>
    <Head title="Presensi Asrama" />

    <AppShell :nav-items="navigation" brand-name="Kepengasuhan">
        <div class="space-y-5">
            <PageHeader kicker="Presensi Malam" title="Presensi Asrama" :subtitle="kamar" />

            <SegmentedControl v-model="slot" :options="slotOptions" />

            <!-- Ringkasan cepat -->
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100">Hadir {{ ringkasan.hadir }}</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-100">Izin {{ ringkasan.izin }}</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-100">Sakit {{ ringkasan.sakit }}</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-100">Alpa {{ ringkasan.alpa }}</span>
            </div>

            <SectionCard kicker="Daftar Santri" title="Ketuk untuk ubah status" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="s in santri" :key="s.id">
                        <template #leading><IconCircle icon="user" tone="slate" /></template>
                        <template #title>{{ s.nama }}</template>
                        <template #subtitle>{{ s.kelas }}</template>
                        <template #trailing>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset transition-all duration-200 active:scale-[.96]"
                                :class="metaFor(presensi[s.id]).ring"
                                @click="cycle(s.id)"
                            >
                                {{ metaFor(presensi[s.id]).label }}
                            </button>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <UiButton type="button" variant="primary" block :loading="saving" icon-left="check" @click="simpan">
                Simpan Presensi
            </UiButton>

            <p v-if="tersimpan" class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-100">
                Presensi {{ slot }} tersimpan. {{ ringkasan.hadir }} hadir dari {{ santri.length }} santri.
            </p>
        </div>
    </AppShell>
</template>
