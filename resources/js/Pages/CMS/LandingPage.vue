<script setup>
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    tenant: {
        type: Object,
        required: true,
    },
    website: {
        type: Object,
        required: true,
    },
    blog: {
        type: Object,
        default: () => ({
            indexUrl: '/#blog',
            hasPublishedArticles: false,
        }),
    },
    jadwalSholat: {
        type: Array,
        default: () => [],
    },
    arahKiblat: {
        type: [Number, null],
        default: null,
    },
    donasiUrl: {
        type: [String, null],
        default: null,
    },
})

const seo = props.website.seo ?? {}
const schemaMarkup = seo.schema ? JSON.stringify(seo.schema) : null
const branding = props.website.branding ?? {}
const websiteContact = props.website.contact ?? { address: '', phone: '', email: '' }

const sections = props.website.sections ?? []
const heroSection = sections.find((s) => s.type === 'hero') ?? null
const heroStats = heroSection?.config?.stats ?? []
const contentSections = sections.filter((s) => s.type !== 'hero')

const has = (type) => sections.some((s) => s.type === type)
const renderSectionTitle = (section) => section.config?.title ?? section.type
const resolveSectionContact = (section) => section.config?.contact ?? websiteContact

const sectionAnchor = (type) => ({
    profile: 'program',
    blog: 'blog',
    gallery: 'galeri',
    faq: 'faq',
}[type] ?? undefined)

const brandStyle = {
    '--brand-primary': branding.primaryColor || '#059669',
    '--brand-secondary': branding.secondaryColor || '#0f766e',
    fontFamily: "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif",
}
const softTint = 'color-mix(in srgb, var(--brand-primary) 10%, #ffffff)'
</script>

<template>
    <div>
        <Head>
            <title>{{ seo.title || branding.name }}</title>
            <meta head-key="description" name="description" :content="seo.description || branding.tagline">
            <meta head-key="robots" name="robots" :content="seo.robots || 'index,follow'">
            <link head-key="canonical" rel="canonical" :href="seo.canonicalUrl || '/'">
            <link v-if="branding.favicon" head-key="favicon" rel="icon" :href="branding.favicon">
            <link v-else head-key="favicon" rel="icon" type="image/svg+xml" href="/favicon.ico">
            <link rel="preconnect" href="https://fonts.bunny.net">
            <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet">
            <meta head-key="og:title" property="og:title" :content="seo.title || branding.name">
            <meta head-key="og:description" property="og:description" :content="seo.description || branding.tagline">
            <meta head-key="og:type" property="og:type" content="website">
            <meta head-key="og:url" property="og:url" :content="seo.canonicalUrl || '/'">
            <meta head-key="og:site_name" property="og:site_name" :content="seo.siteName || branding.name">
            <meta v-if="seo.imageUrl" head-key="og:image" property="og:image" :content="seo.imageUrl">
            <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">
            <meta head-key="twitter:title" name="twitter:title" :content="seo.title || branding.name">
            <meta head-key="twitter:description" name="twitter:description" :content="seo.description || branding.tagline">
            <meta v-if="seo.imageUrl" head-key="twitter:image" name="twitter:image" :content="seo.imageUrl">
            <script
                v-if="schemaMarkup"
                head-key="schema"
                type="application/ld+json"
                v-text="schemaMarkup"
            />
        </Head>

        <div class="min-h-screen bg-[#fafbfc] text-slate-800 antialiased" :style="brandStyle">
            <!-- NAV -->
            <nav class="fixed inset-x-0 top-0 z-50 border-b border-slate-200/70 bg-white/80 backdrop-blur-md">
                <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
                    <a href="#top" class="flex items-center gap-2.5">
                        <img
                            v-if="branding.logoUrl"
                            :src="branding.logoUrl"
                            :alt="branding.name"
                            class="h-9 w-auto object-contain"
                        >
                        <span class="text-lg font-extrabold tracking-tight text-slate-900">{{ branding.name }}</span>
                    </a>

                    <div class="flex items-center gap-7">
                        <div class="hidden items-center gap-7 text-sm font-medium text-slate-600 md:flex">
                            <a v-if="has('profile')" href="#program" class="transition hover:text-[color:var(--brand-primary)]">Profil</a>
                            <a v-if="has('blog')" href="#blog" class="transition hover:text-[color:var(--brand-primary)]">Artikel</a>
                            <a v-if="has('gallery')" href="#galeri" class="transition hover:text-[color:var(--brand-primary)]">Galeri</a>
                            <a v-if="has('faq')" href="#faq" class="transition hover:text-[color:var(--brand-primary)]">FAQ</a>
                            <a href="#kontak" class="transition hover:text-[color:var(--brand-primary)]">Kontak</a>
                            <a href="/jemaah/login" class="transition hover:text-[color:var(--brand-primary)]">Jemaah</a>
                        </div>
                        <a
                            href="/masuk"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-300"
                        >
                            Masuk
                        </a>
                        <a
                            href="/ppdb"
                            class="rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:opacity-95"
                            :style="{ backgroundColor: 'var(--brand-primary)' }"
                        >
                            PPDB Online
                        </a>
                    </div>
                </div>
            </nav>

            <!-- HERO -->
            <header id="top" class="relative overflow-hidden">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-[420px] bg-[linear-gradient(180deg,#ffffff_0%,#fafbfc_100%)]"></div>
                <div class="relative mx-auto max-w-3xl px-5 pb-12 pt-32 text-center sm:px-8 sm:pt-36">
                    <span
                        v-if="heroSection?.config?.eyebrow"
                        class="inline-block rounded-full px-4 py-1.5 text-xs font-semibold uppercase tracking-wide"
                        :style="{ color: 'var(--brand-primary)', backgroundColor: softTint }"
                    >
                        {{ heroSection.config.eyebrow }}
                    </span>
                    <h1 class="mt-5 text-4xl font-extrabold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl">
                        {{ heroSection?.config?.title || branding.name }}
                    </h1>
                    <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-500">
                        {{ heroSection?.config?.description || branding.tagline }}
                    </p>
                    <div class="mt-8 flex flex-wrap justify-center gap-3">
                        <a
                            :href="heroSection?.config?.primaryCtaHref || '/ppdb'"
                            class="inline-flex items-center rounded-lg px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:opacity-95"
                            :style="{ backgroundColor: 'var(--brand-primary)' }"
                        >
                            {{ heroSection?.config?.primaryCtaLabel || 'Daftar PPDB' }}
                        </a>
                        <a
                            :href="heroSection?.config?.secondaryCtaHref || '#kontak'"
                            class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-300"
                        >
                            {{ heroSection?.config?.secondaryCtaLabel || 'Hubungi Kami' }}
                        </a>
                    </div>
                </div>

                <!-- STATS -->
                <div v-if="heroStats.length" class="relative mx-auto mb-4 flex max-w-3xl flex-wrap justify-center gap-x-14 gap-y-6 px-5 pb-10 sm:px-8">
                    <div v-for="stat in heroStats" :key="stat.label" class="text-center">
                        <div class="text-3xl font-extrabold" :style="{ color: 'var(--brand-primary)' }">{{ stat.value }}</div>
                        <div class="mt-1 text-sm text-slate-500">{{ stat.label }}</div>
                    </div>
                </div>
            </header>

            <!-- IBADAH: jadwal sholat + arah kiblat + donasi (Beranda publik, 1-aplikasi) -->
            <section v-if="jadwalSholat.length || donasiUrl" id="ibadah" class="bg-white">
                <div class="mx-auto max-w-6xl px-5 py-16 sm:px-8 sm:py-20">
                    <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr] lg:items-stretch">
                        <div v-if="jadwalSholat.length" class="rounded-2xl border border-slate-100 bg-[#fafbfc] p-6 sm:p-7">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-bold uppercase tracking-[0.2em]" :style="{ color: 'var(--brand-primary)' }">Jadwal Sholat Hari Ini</p>
                                <span v-if="arahKiblat !== null" class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                                    Arah kiblat {{ arahKiblat }}° dari utara
                                </span>
                            </div>
                            <div class="mt-5 grid grid-cols-5 gap-2 text-center sm:gap-3">
                                <div v-for="p in jadwalSholat" :key="p.key" class="rounded-xl bg-white p-3 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                                    <p class="text-[11px] font-medium text-slate-500">{{ p.label }}</p>
                                    <p class="mt-1 text-base font-extrabold text-slate-900 sm:text-lg">{{ p.time }}</p>
                                </div>
                            </div>
                            <p class="mt-4 text-xs text-slate-400">Dihitung otomatis dari lokasi pesantren (metode standar Kemenag).</p>
                        </div>

                        <div v-if="donasiUrl" class="flex flex-col justify-center rounded-2xl p-6 text-white sm:p-7" :style="{ backgroundColor: 'var(--brand-primary)' }">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-white/70">Donasi · Infaq · Zakat</p>
                            <h3 class="mt-2 text-2xl font-extrabold leading-tight">Salurkan kebaikan Anda</h3>
                            <p class="mt-2 text-sm leading-relaxed text-white/85">Dukung program &amp; operasional pondok. Setiap donasi tercatat dan dikonfirmasi pengurus.</p>
                            <a :href="donasiUrl" class="mt-5 inline-flex w-fit items-center rounded-lg bg-white px-5 py-2.5 text-sm font-semibold shadow-sm transition hover:-translate-y-0.5" :style="{ color: 'var(--brand-primary)' }">
                                Donasi Sekarang
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CONTENT SECTIONS -->
            <template v-for="section in contentSections" :key="`${section.type}-${section.order}`">
                <!-- PROFILE -->
                <section
                    v-if="section.type === 'profile'"
                    :id="sectionAnchor('profile')"
                    class="bg-white"
                >
                    <div class="mx-auto max-w-6xl px-5 py-16 sm:px-8 sm:py-20">
                        <div class="grid gap-10 lg:grid-cols-[1fr_0.85fr] lg:items-start">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.2em]" :style="{ color: 'var(--brand-primary)' }">Profil</p>
                                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                                    {{ renderSectionTitle(section) }}
                                </h2>
                                <p class="mt-4 text-base leading-relaxed text-slate-500">{{ section.config.about }}</p>
                                <div v-if="section.config.vision" class="mt-6 rounded-2xl border border-slate-100 bg-[#fafbfc] p-6">
                                    <p class="text-xs font-bold uppercase tracking-[0.2em]" :style="{ color: 'var(--brand-primary)' }">Visi</p>
                                    <p class="mt-2 leading-relaxed text-slate-600">{{ section.config.vision }}</p>
                                </div>
                            </div>

                            <div class="grid gap-4">
                                <div v-if="(section.config.mission || []).length" class="rounded-2xl bg-slate-900 p-6 text-white">
                                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Misi</p>
                                    <ul class="mt-4 grid gap-2.5 text-sm leading-6 text-white/85">
                                        <li v-for="mission in section.config.mission" :key="mission" class="flex gap-2.5">
                                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: 'var(--brand-primary)' }"></span>
                                            <span>{{ mission }}</span>
                                        </li>
                                    </ul>
                                </div>
                                <div
                                    v-for="highlight in section.config.highlights || []"
                                    :key="highlight"
                                    class="rounded-2xl border border-slate-100 bg-white p-4 text-sm leading-6 text-slate-600 shadow-[0_1px_2px_rgba(15,23,42,0.04)]"
                                >
                                    {{ highlight }}
                                </div>
                                <div v-if="resolveSectionContact(section).address || resolveSectionContact(section).phone" class="rounded-2xl border border-slate-100 bg-[#fafbfc] p-5">
                                    <p class="text-xs font-bold uppercase tracking-[0.2em]" :style="{ color: 'var(--brand-primary)' }">Kontak</p>
                                    <div class="mt-3 grid gap-1.5 text-sm leading-6 text-slate-600">
                                        <p>{{ resolveSectionContact(section).address }}</p>
                                        <p v-if="resolveSectionContact(section).phone">Telp: {{ resolveSectionContact(section).phone }}</p>
                                        <p v-if="resolveSectionContact(section).email">Email: {{ resolveSectionContact(section).email }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- BLOG -->
                <section v-else-if="section.type === 'blog'" :id="sectionAnchor('blog')" class="bg-[#fafbfc]">
                    <div class="mx-auto max-w-6xl px-5 py-16 sm:px-8 sm:py-20">
                        <div class="mx-auto mb-12 max-w-2xl text-center">
                            <p class="text-xs font-bold uppercase tracking-[0.2em]" :style="{ color: 'var(--brand-primary)' }">Artikel</p>
                            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ renderSectionTitle(section) }}</h2>
                            <p class="mt-3 text-slate-500">{{ section.config.description }}</p>
                        </div>

                        <div v-if="(section.config.articles || []).length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <a
                                v-for="article in section.config.articles"
                                :key="article.slug"
                                :href="article.href"
                                class="group flex flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(15,23,42,0.08)]"
                            >
                                <div v-if="article.featuredImageUrl" class="aspect-[16/10] overflow-hidden bg-slate-100">
                                    <img :src="article.featuredImageUrl" :alt="article.title" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                </div>
                                <div class="flex flex-1 flex-col gap-2 p-5">
                                    <p v-if="article.publishedAt" class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ article.publishedAt }}</p>
                                    <h3 class="text-lg font-bold leading-snug text-slate-900">{{ article.title }}</h3>
                                    <p class="line-clamp-3 text-sm leading-6 text-slate-500">{{ article.excerpt }}</p>
                                    <span class="mt-auto pt-2 text-sm font-semibold" :style="{ color: 'var(--brand-primary)' }">Baca selengkapnya →</span>
                                </div>
                            </a>
                        </div>
                        <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">
                            {{ section.config.emptyState }}
                        </div>
                    </div>
                </section>

                <!-- GALLERY -->
                <section v-else-if="section.type === 'gallery'" :id="sectionAnchor('gallery')" class="bg-white">
                    <div class="mx-auto max-w-6xl px-5 py-16 sm:px-8 sm:py-20">
                        <div class="mx-auto mb-12 max-w-2xl text-center">
                            <p class="text-xs font-bold uppercase tracking-[0.2em]" :style="{ color: 'var(--brand-primary)' }">Galeri</p>
                            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ renderSectionTitle(section) }}</h2>
                            <p class="mt-3 text-slate-500">{{ section.config.description }}</p>
                        </div>

                        <div v-if="(section.config.galleries || []).length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <article
                                v-for="gallery in section.config.galleries"
                                :key="gallery.slug"
                                class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]"
                            >
                                <div v-if="gallery.coverImageUrl" class="aspect-[16/10] overflow-hidden bg-slate-100">
                                    <img :src="gallery.coverImageUrl" :alt="gallery.title" class="h-full w-full object-cover">
                                </div>
                                <div class="p-5">
                                    <p v-if="gallery.publishedAt" class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ gallery.publishedAt }}</p>
                                    <h3 class="mt-1 text-lg font-bold leading-snug text-slate-900">{{ gallery.title }}</h3>
                                    <p class="mt-1 text-sm leading-6 text-slate-500">{{ gallery.description }}</p>
                                    <div v-if="(gallery.images || []).length" class="mt-4 grid grid-cols-3 gap-2">
                                        <div v-for="(image, index) in gallery.images.slice(0, 3)" :key="`${gallery.slug}-${index}`" class="aspect-square overflow-hidden rounded-lg bg-slate-100">
                                            <img :src="image.imageUrl" :alt="image.caption || gallery.title" class="h-full w-full object-cover">
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                        <div v-else class="rounded-2xl border border-dashed border-slate-200 px-6 py-10 text-center text-sm text-slate-500">
                            {{ section.config.emptyState }}
                        </div>
                    </div>
                </section>

                <!-- FAQ -->
                <section v-else-if="section.type === 'faq'" :id="sectionAnchor('faq')" class="bg-[#fafbfc]">
                    <div class="mx-auto max-w-3xl px-5 py-16 sm:px-8 sm:py-20">
                        <div class="mb-10 text-center">
                            <p class="text-xs font-bold uppercase tracking-[0.2em]" :style="{ color: 'var(--brand-primary)' }">FAQ</p>
                            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ renderSectionTitle(section) }}</h2>
                            <p class="mt-3 text-slate-500">{{ section.config.description }}</p>
                        </div>

                        <div v-if="(section.config.items || []).length" class="grid gap-3">
                            <details
                                v-for="item in section.config.items"
                                :key="item.question"
                                class="group rounded-2xl border border-slate-100 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]"
                            >
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900">
                                    <span>{{ item.question }}</span>
                                    <span class="shrink-0 text-xl leading-none transition group-open:rotate-45" :style="{ color: 'var(--brand-primary)' }">+</span>
                                </summary>
                                <p class="mt-3 text-sm leading-7 text-slate-600">{{ item.answer }}</p>
                            </details>
                        </div>
                        <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">
                            {{ section.config.emptyState }}
                        </div>
                    </div>
                </section>

                <!-- TESTIMONIAL -->
                <section v-else-if="section.type === 'testimonial'" class="bg-white">
                    <div class="mx-auto max-w-4xl px-5 py-16 sm:px-8 sm:py-20">
                        <div class="rounded-3xl bg-slate-900 px-6 py-12 text-center text-white sm:px-12">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-white/50">{{ section.config.title }}</p>
                            <blockquote class="mx-auto mt-5 max-w-2xl text-2xl font-medium leading-relaxed sm:text-[1.7rem]">
                                “{{ section.config.quote }}”
                            </blockquote>
                            <div class="mt-7">
                                <p class="font-bold">{{ section.config.leaderName }}</p>
                                <p class="text-sm text-white/60">{{ section.config.leaderRole }}</p>
                            </div>
                            <div v-if="(section.config.highlights || []).length" class="mx-auto mt-8 grid max-w-2xl gap-2 sm:grid-cols-3">
                                <div v-for="highlight in section.config.highlights" :key="highlight" class="rounded-xl border border-white/10 bg-white/5 px-3 py-3 text-xs leading-5 text-white/80">
                                    {{ highlight }}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- PPDB CTA -->
                <section v-else-if="section.type === 'ppdb_cta'" class="bg-[#fafbfc]">
                    <div class="mx-auto max-w-6xl px-5 sm:px-8">
                        <div class="overflow-hidden rounded-3xl px-6 py-14 text-center sm:px-12" :style="{ background: 'linear-gradient(135deg, var(--brand-primary), var(--brand-secondary))' }">
                            <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">{{ renderSectionTitle(section) }}</h2>
                            <p class="mx-auto mt-3 max-w-xl text-base leading-relaxed text-white/85">{{ section.config.body }}</p>
                            <p v-if="section.config.helperText" class="mx-auto mt-2 max-w-xl text-sm text-white/70">{{ section.config.helperText }}</p>
                            <a
                                :href="section.config.buttonHref || '/ppdb'"
                                class="mt-7 inline-flex items-center rounded-lg bg-white px-6 py-3 text-sm font-bold shadow-sm transition hover:-translate-y-0.5"
                                :style="{ color: 'var(--brand-primary)' }"
                            >
                                {{ section.config.buttonLabel || 'Buka Form PPDB' }}
                            </a>
                        </div>
                    </div>
                    <div class="h-16"></div>
                </section>

                <!-- FALLBACK -->
                <section v-else class="bg-white">
                    <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8">
                        <p class="text-xs font-bold uppercase tracking-[0.2em]" :style="{ color: 'var(--brand-primary)' }">{{ section.type }}</p>
                        <h2 class="mt-2 text-2xl font-extrabold text-slate-900">{{ renderSectionTitle(section) }}</h2>
                    </div>
                </section>
            </template>

            <!-- FOOTER -->
            <footer id="kontak" class="bg-slate-900 text-slate-400">
                <div class="mx-auto grid max-w-6xl gap-8 px-5 py-12 sm:px-8 lg:grid-cols-[1.2fr_0.8fr]">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <img v-if="branding.logoUrl" :src="branding.logoUrl" :alt="branding.name" class="h-9 w-auto object-contain">
                            <span class="text-lg font-extrabold tracking-tight text-white">{{ branding.name }}</span>
                        </div>
                        <p class="mt-4 max-w-md text-sm leading-7 text-slate-400">{{ websiteContact.address }}</p>
                    </div>
                    <div class="grid gap-2 text-sm sm:justify-end">
                        <p v-if="websiteContact.phone" class="sm:text-right"><span class="text-slate-500">Telp:</span> {{ websiteContact.phone }}</p>
                        <p v-if="websiteContact.email" class="sm:text-right"><span class="text-slate-500">Email:</span> {{ websiteContact.email }}</p>
                        <a href="/ppdb" class="mt-2 font-semibold text-white transition hover:opacity-80 sm:text-right">Daftar PPDB Online →</a>
                    </div>
                </div>
                <div class="border-t border-white/10">
                    <div class="mx-auto max-w-6xl px-5 py-5 text-xs text-slate-500 sm:px-8">
                        © {{ new Date().getFullYear() }} {{ branding.name }}. Seluruh hak cipta dilindungi.
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>
