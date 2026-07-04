<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, UiButton, IconCircle, EmptyState, BottomSheet } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    tamu: { type: Array, default: () => [] },
    jengukHariIni: { type: Array, default: () => [] },
    walkInBoleh: { type: Boolean, default: false },
})

const jumlahHadir = computed(() => props.tamu.filter((t) => t.hadir).length)

const sheetOpen = ref(false)
const dipilih = ref(null)
const mengirim = ref(false)

function konfirmasi(p) {
    dipilih.value = p
    sheetOpen.value = true
}

function tandaiHadir() {
    if (!dipilih.value) return
    mengirim.value = true
    router.post(`/keamanan/tamu/${dipilih.value.id}/hadir`, {}, {
        preserveScroll: true,
        onFinish: () => {
            mengirim.value = false
            sheetOpen.value = false
        },
    })
}
</script>

<template>
    <Head title="Buku Tamu · Pos Keamanan" />

    <AppShell :nav-items="navigation" brand-name="Pos Keamanan">
        <div class="space-y-4">
            <PageHeader
                kicker="Kunjungan"
                title="Buku Tamu"
                :subtitle="`${jumlahHadir} dari ${tamu.length} tamu tercatat hadir hari ini`"
            />

            <SectionCard v-if="jengukHariIni.length" title="Jenguk Terjadwal — Belum Hadir" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="p in jengukHariIni"
                        :key="p.id"
                        :title="p.pengaju ?? '—'"
                        :subtitle="`Menjenguk: ${p.santri ?? '—'}`"
                    >
                        <template #leading>
                            <IconCircle icon="user" tone="amber" size="md" />
                        </template>
                        <template #trailing>
                            <UiButton variant="primary" size="sm" icon-left="check" @click="konfirmasi(p)">
                                Tandai Hadir
                            </UiButton>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <EmptyState
                v-if="!tamu.length"
                icon="user"
                title="Belum ada tamu"
                message="Pengunjung yang tercatat hari ini akan tampil di sini."
            />

            <SectionCard v-else title="Log Kunjungan Hari Ini" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="t in tamu"
                        :key="t.id"
                        :title="t.nama"
                        :subtitle="`${t.asal} · ${t.keperluan}`"
                    >
                        <template #leading>
                            <IconCircle icon="user" :tone="t.hadir ? 'emerald' : 'slate'" size="md" />
                        </template>
                        <template #default>
                            <p class="text-xs text-slate-500">
                                Menjenguk: <span class="font-semibold text-slate-700">{{ t.dijenguk }}</span>
                            </p>
                            <div class="mt-2">
                                <StatusBadge
                                    :status="t.hadir ? 'aktif' : 'menunggu'"
                                    :label="t.hadir ? `Hadir · ${t.jam}` : 'Belum hadir'"
                                    size="sm"
                                />
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </div>

        <BottomSheet v-model="sheetOpen" title="Tandai Tamu Hadir">
            <div v-if="dipilih" class="space-y-3">
                <div class="flex items-center gap-3">
                    <IconCircle icon="user" tone="emerald" />
                    <div class="min-w-0">
                        <p class="truncate font-jost text-[15px] font-bold text-slate-900">{{ dipilih.pengaju }}</p>
                        <p class="text-xs text-slate-500">Menjenguk {{ dipilih.santri }}</p>
                    </div>
                </div>
                <p class="text-sm leading-relaxed text-slate-600">
                    Catat kehadiran tamu ini di pos keamanan sekarang?
                </p>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block :disabled="mengirim" @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block :disabled="mengirim" @click="tandaiHadir">Tandai Hadir</UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
