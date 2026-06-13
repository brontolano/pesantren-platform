<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed, reactive } from 'vue'
import {
    AppShell, PageHeader, SectionCard, ListRow, StatusBadge,
    SegmentedControl, UiButton, BottomSheet, EmptyState,
} from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    artikel: { type: Array, default: () => [] },
})

const navItems = props.navigation

const COVER = {
    emerald: 'bg-emerald-100 text-emerald-700',
    teal: 'bg-teal-100 text-teal-700',
    amber: 'bg-amber-100 text-amber-700',
    blue: 'bg-blue-100 text-blue-700',
    rose: 'bg-rose-100 text-rose-700',
    slate: 'bg-slate-100 text-slate-600',
}

const filter = ref('berita')
const segmentOptions = [
    { value: 'berita', label: 'Berita' },
    { value: 'pengumuman', label: 'Pengumuman' },
    { value: 'prestasi', label: 'Prestasi' },
]

// Salinan lokal reaktif agar toggle publikasi bersifat optimistic (tanpa POST).
const items = reactive(props.artikel.map((a) => ({ ...a })))

const tersaring = computed(() => items.filter((a) => a.kategori === filter.value))

// Konfirmasi toggle via BottomSheet.
const sheetOpen = ref(false)
const target = ref(null)
const toast = ref('')

function mintaToggle(a) {
    target.value = a
    sheetOpen.value = true
}

function konfirmasiToggle() {
    const a = target.value
    if (a) {
        const row = items.find((x) => x.id === a.id)
        if (row) {
            row.status = row.status === 'published' ? 'draft' : 'published'
            toast.value = row.status === 'published'
                ? `"${row.judul}" dipublikasikan.`
                : `"${row.judul}" dijadikan draf.`
        }
    }
    sheetOpen.value = false
    setTimeout(() => { toast.value = '' }, 2600)
}

const aksiLabel = computed(() =>
    target.value?.status === 'published' ? 'Jadikan Draf' : 'Publikasikan',
)
</script>

<template>
    <Head title="Berita & Pengumuman" />

    <AppShell :nav-items="navItems" brand-name="Humas Pesantren">
        <div class="space-y-5">
            <PageHeader
                kicker="Konten"
                title="Berita & Publikasi"
                subtitle="Kelola artikel berita, pengumuman, dan prestasi"
            />

            <SegmentedControl v-model="filter" :options="segmentOptions" />

            <div
                v-if="toast"
                class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-100"
                role="status"
            >
                {{ toast }}
            </div>

            <SectionCard v-if="tersaring.length" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="a in tersaring" :key="a.id" :href="`/humas/berita/${a.id}`" chevron>
                        <template #leading>
                            <span
                                class="flex h-11 w-11 items-center justify-center rounded-2xl text-sm font-bold ring-1 ring-inset ring-black/5"
                                :class="COVER[a.warna] || COVER.slate"
                            >
                                {{ a.inisial }}
                            </span>
                        </template>
                        <template #title>{{ a.judul }}</template>
                        <template #subtitle>{{ a.penulis }} &middot; {{ a.tanggal }}</template>
                        <template #trailing>
                            <StatusBadge
                                :status="a.status === 'published' ? 'aktif' : 'pending'"
                                :label="a.status === 'published' ? 'Terbit' : 'Draf'"
                                size="sm"
                            />
                        </template>
                        <div class="flex justify-end pt-1">
                            <UiButton
                                size="sm"
                                :variant="a.status === 'published' ? 'secondary' : 'primary'"
                                :icon-left="a.status === 'published' ? 'minus' : 'arrow-up-right'"
                                @click.prevent="mintaToggle(a)"
                            >
                                {{ a.status === 'published' ? 'Tarik dari publik' : 'Publikasikan' }}
                            </UiButton>
                        </div>
                    </ListRow>
                </div>
            </SectionCard>

            <EmptyState
                v-else
                icon="document"
                title="Belum ada konten"
                :message="`Belum ada artikel pada kategori ini.`"
            />
        </div>

        <BottomSheet v-model="sheetOpen" :title="aksiLabel">
            <p class="text-sm leading-6 text-slate-600">
                <template v-if="target?.status === 'published'">
                    Artikel <span class="font-semibold text-slate-900">"{{ target?.judul }}"</span>
                    akan ditarik dari halaman publik dan kembali menjadi draf.
                </template>
                <template v-else>
                    Artikel <span class="font-semibold text-slate-900">"{{ target?.judul }}"</span>
                    akan tampil di website publik pesantren.
                </template>
            </p>
            <p class="mt-2 text-xs text-slate-400">Mode demo — perubahan hanya tampil di layar ini.</p>

            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" block icon-left="check" @click="konfirmasiToggle">
                        {{ aksiLabel }}
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
