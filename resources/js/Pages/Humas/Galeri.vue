<script setup>
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import { AppShell, PageHeader, BottomSheet, EmptyState, UiIcon } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    album: { type: Array, default: () => [] },
})

const navItems = props.navigation

const COVER = {
    emerald: 'from-violet-400 to-teal-500',
    teal: 'from-teal-400 to-violet-500',
    amber: 'from-amber-400 to-orange-500',
    blue: 'from-blue-400 to-indigo-500',
    rose: 'from-rose-400 to-pink-500',
    slate: 'from-slate-400 to-slate-500',
}

const sheetOpen = ref(false)
const aktif = ref(null)

function bukaAlbum(a) {
    aktif.value = a
    sheetOpen.value = true
}
</script>

<template>
    <Head title="Galeri Foto" />

    <AppShell :nav-items="navItems" brand-name="Humas Pesantren">
        <div class="space-y-5">
            <PageHeader
                kicker="Dokumentasi"
                title="Galeri Album"
                subtitle="Kumpulan foto kegiatan pesantren"
            />

            <div v-if="album.length" class="grid grid-cols-2 gap-3">
                <button
                    v-for="a in album"
                    :key="a.id"
                    type="button"
                    class="group flex flex-col overflow-hidden rounded-3xl bg-white text-left ring-1 ring-slate-100 shadow-soft transition-all duration-200 active:scale-[.98]"
                    @click="bukaAlbum(a)"
                >
                    <div
                        class="relative flex h-28 items-center justify-center bg-gradient-to-br"
                        :class="COVER[a.warna] || COVER.slate"
                    >
                        <span class="text-2xl font-extrabold text-white/90">{{ a.inisial }}</span>
                        <span class="absolute bottom-2 right-2 inline-flex items-center gap-1 rounded-full bg-black/30 px-2 py-0.5 text-[11px] font-semibold text-white backdrop-blur-sm">
                            <UiIcon name="camera" class="h-3 w-3" />
                            {{ a.jumlah }}
                        </span>
                    </div>
                    <div class="p-3">
                        <p class="truncate text-[13.5px] font-bold leading-tight text-slate-900">{{ a.judul }}</p>
                        <p class="mt-0.5 text-xs text-slate-400">{{ a.tanggal }}</p>
                    </div>
                </button>
            </div>

            <EmptyState
                v-else
                icon="camera"
                title="Belum ada album"
                message="Album foto kegiatan akan tampil di sini."
            />
        </div>

        <BottomSheet v-model="sheetOpen" :title="aktif?.judul || 'Album'">
            <p class="mb-3 text-xs text-slate-400">
                {{ aktif?.jumlah }} foto &middot; {{ aktif?.tanggal }}
            </p>
            <div class="grid grid-cols-3 gap-2">
                <div
                    v-for="f in (aktif?.foto || [])"
                    :key="f.id"
                    class="flex aspect-square items-center justify-center rounded-2xl bg-gradient-to-br text-xs font-semibold text-white/90"
                    :class="COVER[f.warna] || COVER.slate"
                    :title="f.caption"
                >
                    <UiIcon name="camera" class="h-5 w-5 opacity-80" />
                </div>
            </div>
            <p class="mt-3 text-xs leading-5 text-slate-400">
                Pratinjau foto placeholder — peragaan demo.
            </p>
        </BottomSheet>
    </AppShell>
</template>
