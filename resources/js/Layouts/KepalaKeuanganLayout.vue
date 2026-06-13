<script setup>
import { computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { AppShell, HeroHeader } from '@/Shared'

const page = usePage()
const branding = computed(() => page.props.branding ?? {})
const userName = computed(() => page.props.userName ?? '')
const brandName = computed(() => branding.value.name || 'Pesantren')
const faviconUrl = computed(() => branding.value.faviconUrl || branding.value.icon192Url || '/favicon.ico')

const navItems = computed(() => [
    { key: 'beranda', label: 'Beranda', href: '/keuangan', active: page.component === 'KepalaKeuangan/Beranda' },
    { key: 'penerimaan', label: 'Penerimaan', href: '/keuangan/penerimaan/tagihan', active: page.component.startsWith('KepalaKeuangan/Penerimaan') },
    { key: 'pengeluaran', label: 'Pengeluaran', href: '/keuangan/pengeluaran/approval', active: page.component.startsWith('KepalaKeuangan/Pengeluaran') },
    { key: 'ledger', label: 'Ledger', href: '/keuangan/ledger/jurnal', active: page.component.startsWith('KepalaKeuangan/Ledger') },
    { key: 'laporan', label: 'Laporan', href: '/keuangan/laporan', active: page.component.startsWith('KepalaKeuangan/Laporan') },
])

// Ikon dipetakan ke whitelist UiIcon (penerimaan→cash, pengeluaran→receipt, ledger→bank).
const navIcons = { beranda: 'home', penerimaan: 'cash', pengeluaran: 'receipt', ledger: 'bank', laporan: 'chart' }

const isHome = computed(() => page.component === 'KepalaKeuangan/Beranda')

onMounted(async () => {
    if (typeof window !== 'undefined' && window.location.pathname.startsWith('/keuangan')) {
        try {
            const { registerKeuanganPwa } = await import('@/keuangan-pwa/registerPwa.js')
            void registerKeuanganPwa()
        } catch {
            // registerPwa belum ada — skip
        }
    }
})
</script>

<template>
    <AppShell
        :nav-items="navItems"
        :nav-icons="navIcons"
        :brand-name="`${brandName} · Keuangan`"
        theme-color="#059669"
        manifest-href="/keuangan/manifest.json"
        :favicon-url="faviconUrl"
        :apple-touch-icon="branding.icon192Url || faviconUrl"
        offline-message="Anda sedang offline. Data mungkin tidak terkini."
    >
        <template v-if="isHome" #header>
            <HeroHeader :name="userName || brandName" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-0.5 text-[11px] font-semibold text-white ring-1 ring-inset ring-white/25">
                        Bendahara
                    </span>
                </template>
            </HeroHeader>
        </template>

        <slot />
    </AppShell>
</template>
