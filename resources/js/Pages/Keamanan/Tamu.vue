<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, UiButton, IconCircle, EmptyState, BottomSheet } from '@/Shared'

// Tamu — buku tamu hari ini. "Tandai Hadir" optimistic (tanpa POST).
const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    tamu: { type: Array, default: () => [] },
})

const daftar = ref(props.tamu.map((t) => ({ ...t })))
const sheetOpen = ref(false)
const dipilih = ref(null)
const toast = ref('')

const jumlahHadir = computed(() => daftar.value.filter((t) => t.hadir).length)

function konfirmasi(t) {
    dipilih.value = t
    sheetOpen.value = true
}

function tandaiHadir() {
    const target = daftar.value.find((t) => t.id === dipilih.value.id)
    if (target) {
        target.hadir = true
        target.jam = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
        toast.value = `${target.nama} ditandai hadir.`
    }
    sheetOpen.value = false
    setTimeout(() => (toast.value = ''), 2400)
}
</script>

<template>
    <Head title="Buku Tamu · Pos Keamanan" />

    <AppShell :nav-items="navigation" brand-name="Pos Keamanan">
        <div class="space-y-4">
            <PageHeader
                kicker="Kunjungan"
                title="Buku Tamu"
                :subtitle="`${jumlahHadir} dari ${daftar.length} tamu hadir hari ini`"
            />

            <EmptyState
                v-if="!daftar.length"
                icon="user"
                title="Belum ada tamu"
                message="Pengunjung yang terdaftar hari ini akan tampil di sini."
            />

            <SectionCard v-else title="Daftar Hari Ini" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="t in daftar"
                        :key="t.id"
                        :title="t.nama"
                        :subtitle="`${t.asal} · ${t.keperluan}`"
                    >
                        <template #leading>
                            <IconCircle icon="user" :tone="t.hadir ? 'emerald' : 'slate'" size="md" />
                        </template>
                        <template #default>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs text-slate-500">
                                    Menjenguk: <span class="font-semibold text-slate-700">{{ t.dijenguk }}</span>
                                </p>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <StatusBadge
                                    :status="t.hadir ? 'aktif' : 'menunggu'"
                                    :label="t.hadir ? `Hadir · ${t.jam}` : 'Belum hadir'"
                                    size="sm"
                                />
                                <UiButton
                                    v-if="!t.hadir"
                                    variant="primary"
                                    size="sm"
                                    icon-left="check"
                                    @click="konfirmasi(t)"
                                >
                                    Tandai Hadir
                                </UiButton>
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Mode peragaan — penandaan hadir hanya tersimpan sementara di perangkat ini.
            </p>
        </div>

        <BottomSheet v-model="sheetOpen" title="Tandai Tamu Hadir">
            <div v-if="dipilih" class="space-y-3">
                <div class="flex items-center gap-3">
                    <IconCircle icon="user" tone="emerald" />
                    <div class="min-w-0">
                        <p class="truncate font-jost text-[15px] font-bold text-slate-900">{{ dipilih.nama }}</p>
                        <p class="text-xs text-slate-500">{{ dipilih.asal }} · menjenguk {{ dipilih.dijenguk }}</p>
                    </div>
                </div>
                <p class="text-sm leading-relaxed text-slate-600">
                    Catat kehadiran tamu ini di pos keamanan sekarang?
                </p>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block @click="tandaiHadir">Tandai Hadir</UiButton>
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
