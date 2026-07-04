<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
    AppShell, PageHeader, SectionCard, ListRow, StatusBadge, UiCard,
    UiButton, UiField, UiInput, BottomSheet, IconCircle,
} from '@/Shared'

const props = defineProps({
    calon: { type: Object, default: () => ({}) },
    dokumen: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const navIcons = {
    beranda: 'chart',
    pendaftar: 'user',
    verifikasi: 'check-circle',
    hasil: 'document',
}

const sheetOpen = ref(false)
const aksi = ref('verifikasi') // 'verifikasi' | 'tolak'
const aktif = ref(null)
const catatan = ref('')
const mengirim = ref(false)

function buka(dok, mode) {
    aktif.value = dok
    aksi.value = mode
    catatan.value = ''
    sheetOpen.value = true
}

function konfirmasi() {
    if (!aktif.value) return
    mengirim.value = true
    router.post(`/ppdb-panitia/verifikasi/dokumen/${aktif.value.id}`, {
        status: aksi.value === 'verifikasi' ? 'verified' : 'rejected',
        catatan_verifikasi: catatan.value || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            mengirim.value = false
            sheetOpen.value = false
        },
    })
}
</script>

<template>
    <Head title="Verifikasi Berkas — PPDB Panitia" />

    <AppShell :nav-items="navigation" :nav-icons="navIcons" brand-name="PPDB Panitia">
        <div class="space-y-5">
            <PageHeader
                kicker="Verifikasi"
                title="Verifikasi Berkas"
                subtitle="Tinjau tiap dokumen lalu tandai terverifikasi atau tolak."
            />

            <UiCard>
                <p class="text-base font-bold tracking-tight text-slate-900">{{ calon.nama }}</p>
                <p class="mt-0.5 text-sm text-slate-500">{{ calon.no }} · {{ calon.program }} · Gel. {{ calon.gelombang }}</p>
            </UiCard>

            <SectionCard title="Dokumen" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="d in dokumen" :key="d.id" :title="d.nama" :subtitle="d.berkas">
                        <template #leading>
                            <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-50 text-slate-500">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                            </span>
                        </template>
                        <template #trailing>
                            <StatusBadge :status="d.status" size="sm" />
                        </template>
                        <div class="flex gap-2">
                            <UiButton size="sm" variant="secondary" icon-left="check-circle" @click="buka(d, 'verifikasi')">
                                Verifikasi
                            </UiButton>
                            <UiButton size="sm" variant="danger" icon-left="x-circle" @click="buka(d, 'tolak')">
                                Tolak
                            </UiButton>
                        </div>
                    </ListRow>
                </div>
            </SectionCard>
        </div>

        <!-- BottomSheet konfirmasi -->
        <BottomSheet
            v-model="sheetOpen"
            :title="aksi === 'verifikasi' ? 'Verifikasi Dokumen' : 'Tolak Dokumen'"
        >
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3">
                    <IconCircle
                        :icon="aksi === 'verifikasi' ? 'check-circle' : 'x-circle'"
                        :tone="aksi === 'verifikasi' ? 'emerald' : 'rose'"
                        size="md"
                        variant="tint"
                    />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-900">{{ aktif?.nama }}</p>
                        <p class="truncate text-xs text-slate-500">{{ aktif?.berkas }}</p>
                    </div>
                </div>

                <UiField :label="aksi === 'verifikasi' ? 'Catatan (opsional)' : 'Alasan penolakan'">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            :aria-describedby="describedby"
                            v-model="catatan"
                            type="textarea"
                            :rows="3"
                            :placeholder="aksi === 'verifikasi' ? 'Mis. berkas jelas dan sah' : 'Mis. dokumen buram, mohon unggah ulang'"
                        />
                    </template>
                </UiField>
            </div>

            <template #footer>
                <UiButton
                    block
                    :variant="aksi === 'verifikasi' ? 'primary' : 'danger'"
                    :icon-left="aksi === 'verifikasi' ? 'check' : 'x-circle'"
                    :disabled="mengirim"
                    @click="konfirmasi"
                >
                    {{ aksi === 'verifikasi' ? 'Tandai Terverifikasi' : 'Tolak Dokumen' }}
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
