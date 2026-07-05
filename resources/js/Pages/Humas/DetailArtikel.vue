<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, UiButton, StatusBadge, Chip } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    artikel: { type: Object, default: () => ({}) },
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

const KATEGORI = {
    berita: 'Berita',
    pengumuman: 'Pengumuman',
    prestasi: 'Prestasi',
}

const coverClass = computed(() => COVER[props.artikel.warna] || COVER.slate)
const kategoriLabel = computed(() => KATEGORI[props.artikel.kategori] || 'Artikel')

const tersalin = ref(false)
function salinTautan() {
    const url = typeof window !== 'undefined'
        ? `${window.location.origin}/humas/berita/${props.artikel.id}`
        : `/humas/berita/${props.artikel.id}`
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard.writeText(url).catch(() => {})
    }
    tersalin.value = true
    setTimeout(() => { tersalin.value = false }, 2200)
}
</script>

<template>
    <Head :title="artikel.judul || 'Detail Artikel'" />

    <AppShell :nav-items="navItems" brand-name="Humas Pesantren">
        <div class="space-y-5">
            <UiButton variant="ghost" size="sm" icon-left="arrow-left" href="/humas/berita">
                Kembali ke Berita
            </UiButton>

            <!-- Gambar placeholder warna + inisial (tanpa URL eksternal) -->
            <div
                class="relative flex h-48 w-full items-center justify-center overflow-hidden rounded-3xl bg-gradient-to-br shadow-soft"
                :class="coverClass"
            >
                <span class="text-4xl font-extrabold tracking-tight text-white/90">
                    {{ artikel.inisial || 'PP' }}
                </span>
            </div>

            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Chip :active="true" tone="soft" size="sm">{{ kategoriLabel }}</Chip>
                    <StatusBadge
                        :status="artikel.status === 'published' ? 'aktif' : 'pending'"
                        :label="artikel.status === 'published' ? 'Terbit' : 'Draf'"
                        size="sm"
                    />
                </div>

                <h1 class="font-jost text-xl font-extrabold leading-tight tracking-tight text-slate-900">
                    {{ artikel.judul }}
                </h1>

                <p class="text-sm text-slate-500">
                    {{ artikel.penulis }} &middot; {{ artikel.tanggal }}
                </p>
            </div>

            <article class="rounded-3xl bg-white p-5 text-[15px] leading-7 text-slate-700 ring-1 ring-slate-100 shadow-soft">
                {{ artikel.isi }}
            </article>

            <UiButton
                block
                :variant="tersalin ? 'secondary' : 'primary'"
                :icon-left="tersalin ? 'check-circle' : 'copy'"
                @click="salinTautan"
            >
                {{ tersalin ? 'Tautan tersalin' : 'Salin tautan' }}
            </UiButton>
        </div>
    </AppShell>
</template>
