<script setup>
import { computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { AppShell, HeroHeader } from '@/Shared'

const page = usePage()
const branding = computed(() => page.props.branding ?? {})
const guru = computed(() => page.props.guru ?? {})
const brandName = computed(() => branding.value.name || 'Pesantren')
const faviconUrl = computed(() => branding.value.faviconUrl || branding.value.icon192Url || branding.value.logoUrl || '/favicon.ico')

const navItems = computed(() => [
    { key: 'beranda', label: 'Hari Ini', href: '/guru', active: page.component === 'PortalGuru/Beranda' || page.component === 'PortalGuru/Presensi' },
    { key: 'setoran', label: 'Setoran', href: '/guru/setoran', active: page.component.startsWith('PortalGuru/Setoran') },
    { key: 'pesan', label: 'Pesan Wali', href: '/guru/pesan', active: page.component === 'PortalGuru/PesanWali' },
    { key: 'slip', label: 'Slip Saya', href: '/guru/slip-saya', active: page.component === 'PortalGuru/SlipSaya' },
])

// Ikon bottom-nav khusus peran guru.
const navIcons = { beranda: 'calendar', setoran: 'book', pesan: 'document', slip: 'cash' }

const isHome = computed(() => page.component === 'PortalGuru/Beranda')

onMounted(async () => {
    if (typeof window === 'undefined' || !window.location.pathname.startsWith('/guru')) {
        return
    }
    const { registerGuruPwa } = await import('@/guru/registerPwa.js')
    void registerGuruPwa()
})
</script>

<template>
    <AppShell
        :nav-items="navItems"
        :nav-icons="navIcons"
        :brand-name="brandName"
        :theme-color="branding.primaryColor || '#059669'"
        manifest-href="/guru/manifest.json"
        :favicon-url="faviconUrl"
        :apple-touch-icon="branding.icon192Url || faviconUrl"
        offline-message="Anda sedang offline. Aksi simpan akan dicoba ulang saat jaringan kembali."
    >
        <template v-if="isHome" #header>
            <HeroHeader :name="guru.name || brandName" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-0.5 text-[11px] font-semibold text-white ring-1 ring-inset ring-white/25">
                        Pengajar
                    </span>
                </template>
            </HeroHeader>
        </template>

        <slot />
    </AppShell>
</template>
