<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, StatCard, SectionCard, ListRow, StatusBadge, UiButton, IconCircle, BottomSheet } from '@/Shared'

// Ronda — checklist 6 titik patroli. Toggle "Sudah Diperiksa" optimistic.
const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    titik: { type: Array, default: () => [] },
})

const daftar = ref(props.titik.map((t) => ({ ...t })))
const sheetOpen = ref(false)
const dipilih = ref(null)
const toast = ref('')

const selesai = computed(() => daftar.value.filter((t) => t.diperiksa).length)
const total = computed(() => daftar.value.length)
const tuntas = computed(() => selesai.value === total.value)

function konfirmasi(t) {
    if (t.diperiksa) {
        // Batalkan langsung tanpa sheet.
        t.diperiksa = false
        toast.value = `${t.nama} ditandai belum diperiksa.`
        setTimeout(() => (toast.value = ''), 2200)
        return
    }
    dipilih.value = t
    sheetOpen.value = true
}

function tandaiPeriksa() {
    const target = daftar.value.find((t) => t.id === dipilih.value.id)
    if (target) {
        target.diperiksa = true
        toast.value = `${target.nama} ditandai sudah diperiksa.`
    }
    sheetOpen.value = false
    setTimeout(() => (toast.value = ''), 2400)
}
</script>

<template>
    <Head title="Ronda Malam · Pos Keamanan" />

    <AppShell :nav-items="navigation" brand-name="Pos Keamanan">
        <div class="space-y-4">
            <PageHeader kicker="Patroli" title="Ronda Malam" subtitle="Checklist 6 titik keliling" />

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="check-circle" tone="emerald" label="Sudah Diperiksa" :value="`${selesai}/${total}`" hint="Titik patroli" compact />
                <StatCard icon="clock" :tone="tuntas ? 'emerald' : 'amber'" label="Status Ronda" compact>
                    <template #value>
                        <StatusBadge
                            :status="tuntas ? 'aktif' : 'menunggu'"
                            :label="tuntas ? 'Tuntas' : 'Berjalan'"
                        />
                    </template>
                </StatCard>
            </div>

            <SectionCard title="Titik Patroli" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="t in daftar"
                        :key="t.id"
                        :title="t.nama"
                        :subtitle="`Jadwal pemeriksaan ${t.jam}`"
                    >
                        <template #leading>
                            <IconCircle
                                :icon="t.diperiksa ? 'check-circle' : 'clock'"
                                :tone="t.diperiksa ? 'emerald' : 'slate'"
                                size="md"
                            />
                        </template>
                        <template #trailing>
                            <UiButton
                                :variant="t.diperiksa ? 'secondary' : 'primary'"
                                size="sm"
                                :icon-left="t.diperiksa ? 'check-circle' : 'check'"
                                @click="konfirmasi(t)"
                            >
                                {{ t.diperiksa ? 'Diperiksa' : 'Periksa' }}
                            </UiButton>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Mode peragaan — status ronda hanya tersimpan sementara di perangkat ini.
            </p>
        </div>

        <BottomSheet v-model="sheetOpen" title="Tandai Titik Diperiksa">
            <div v-if="dipilih" class="space-y-3">
                <div class="flex items-center gap-3">
                    <IconCircle icon="check-circle" tone="emerald" />
                    <div class="min-w-0">
                        <p class="truncate font-jost text-[15px] font-bold text-slate-900">{{ dipilih.nama }}</p>
                        <p class="text-xs text-slate-500">Jadwal pemeriksaan {{ dipilih.jam }}</p>
                    </div>
                </div>
                <p class="text-sm leading-relaxed text-slate-600">
                    Konfirmasi titik ini sudah diperiksa dan aman?
                </p>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block @click="tandaiPeriksa">Sudah Diperiksa</UiButton>
                </div>
            </template>
        </BottomSheet>

        <Transition name="toast-pop">
            <p v-if="toast" class="fixed inset-x-0 bottom-28 z-40 mx-auto w-full max-w-md px-6">
                <span class="block rounded-2xl bg-slate-900/90 px-4 py-3 text-sm font-medium text-white shadow-soft-lg backdrop-blur">
                    {{ toast }}
                </span>
            </p>
        </Transition>
    </AppShell>
</template>

<style scoped>
.toast-pop-enter-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.toast-pop-leave-active { transition: all 0.2s ease-in; }
.toast-pop-enter-from, .toast-pop-leave-to { opacity: 0; transform: translateY(12px); }
</style>
