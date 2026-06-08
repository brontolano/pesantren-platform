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
})

const renderSectionTitle = (section) => section.config?.title ?? section.type
const renderHeroDescription = (section) => section.config?.description || props.website.branding.tagline
const seo = props.website.seo ?? {}
const schemaMarkup = seo.schema ? JSON.stringify(seo.schema) : null
const websiteContact = props.website.contact ?? { address: '', phone: '', email: '' }
const resolveSectionContact = (section) => section.config?.contact ?? websiteContact
</script>

<template>
    <div>
        <Head>
            <title>{{ seo.title || website.branding.name }}</title>
            <meta
                head-key="description"
                name="description"
                :content="seo.description || website.branding.tagline"
            >
            <meta head-key="robots" name="robots" :content="seo.robots || 'index,follow'">
            <link head-key="canonical" rel="canonical" :href="seo.canonicalUrl || '/'">
            <link v-if="website.branding.favicon" head-key="favicon" rel="icon" :href="website.branding.favicon">
            <link v-else head-key="favicon" rel="icon" type="image/svg+xml" href="/favicon.ico">
            <meta head-key="og:title" property="og:title" :content="seo.title || website.branding.name">
            <meta head-key="og:description" property="og:description" :content="seo.description || website.branding.tagline">
            <meta head-key="og:type" property="og:type" content="website">
            <meta head-key="og:url" property="og:url" :content="seo.canonicalUrl || '/'">
            <meta head-key="og:site_name" property="og:site_name" :content="seo.siteName || website.branding.name">
            <meta v-if="seo.imageUrl" head-key="og:image" property="og:image" :content="seo.imageUrl">
            <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">
            <meta head-key="twitter:title" name="twitter:title" :content="seo.title || website.branding.name">
            <meta head-key="twitter:description" name="twitter:description" :content="seo.description || website.branding.tagline">
            <meta v-if="seo.imageUrl" head-key="twitter:image" name="twitter:image" :content="seo.imageUrl">
            <script
                v-if="schemaMarkup"
                head-key="schema"
                type="application/ld+json"
                v-text="schemaMarkup"
            />
        </Head>

        <div
            class="min-h-screen bg-[linear-gradient(180deg,#f7f5ef_0%,#fffaf2_45%,#ffffff_100%)] text-slate-900"
            :style="{
                '--brand-primary': website.branding.primaryColor,
                '--brand-secondary': website.branding.secondaryColor,
                '--brand-font-family': website.branding.fontFamily || 'ui-sans-serif, system-ui, sans-serif',
            }"
        >
        <div :style="{ fontFamily: 'var(--brand-font-family)' }">
        <header class="border-b border-black/5 bg-white/80 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <img
                        v-if="website.branding.logoUrl"
                        :src="website.branding.logoUrl"
                        :alt="website.branding.name"
                        class="h-10 w-auto object-contain"
                    >
                    <div>
                        <p v-if="!website.branding.logoUrl" class="text-xs font-semibold uppercase tracking-[0.35em] text-[color:var(--brand-secondary)]">
                            Pesantren
                        </p>
                        <h1 class="font-serif text-2xl font-semibold">
                            {{ website.branding.name }}
                        </h1>
                    </div>
                </div>

                <a
                    href="/ppdb"
                    class="rounded-full px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                    :style="{ backgroundColor: 'var(--brand-primary)' }"
                >
                    PPDB Online
                </a>
            </div>
        </header>

        <main class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8 lg:gap-8 lg:py-10">
            <section
                v-for="section in website.sections"
                :key="`${section.type}-${section.order}`"
                :id="section.type === 'blog' ? 'blog' : undefined"
                class="overflow-hidden rounded-[2rem] border border-black/5 bg-white shadow-[0_20px_60px_-35px_rgba(15,23,42,0.25)]"
            >
                <div
                    v-if="section.type === 'hero'"
                    class="grid gap-8 px-6 py-8 sm:px-8 lg:grid-cols-[1.05fr_0.95fr] lg:px-10 lg:py-12"
                >
                    <div class="space-y-5">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[color:var(--brand-secondary)]">
                            {{ section.config.eyebrow }}
                        </p>
                        <div class="space-y-4">
                            <h2 class="max-w-3xl font-serif text-4xl leading-tight sm:text-5xl">
                                {{ section.config.title }}
                            </h2>
                            <p class="max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                                {{ renderHeroDescription(section) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <a
                                :href="section.config.primaryCtaHref || '/ppdb'"
                                class="rounded-full px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90"
                                :style="{ backgroundColor: 'var(--brand-primary)' }"
                            >
                                {{ section.config.primaryCtaLabel || 'Daftar Sekarang' }}
                            </a>
                            <a
                                :href="section.config.secondaryCtaHref || '#kontak'"
                                class="rounded-full border px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400"
                            >
                                {{ section.config.secondaryCtaLabel || 'Lihat Profil' }}
                            </a>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-[1.75rem] bg-slate-950 text-white">
                        <div
                            v-if="section.config.imageUrl"
                            class="h-56 w-full bg-slate-900 sm:h-64"
                        >
                            <img
                                :src="section.config.imageUrl"
                                :alt="section.config.imageAlt || section.config.title"
                                class="h-full w-full object-cover"
                            >
                        </div>
                        <div class="grid gap-3 px-5 py-5">
                            <p class="text-sm uppercase tracking-[0.25em] text-white/60">
                                Ringkasan Layanan
                            </p>
                            <div
                                v-for="stat in section.config.stats || []"
                                :key="stat.label"
                                class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4"
                            >
                                <p class="text-xs uppercase tracking-[0.25em] text-white/55">
                                    {{ stat.label }}
                                </p>
                                <p class="mt-2 text-lg font-semibold">
                                    {{ stat.value }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="section.type === 'profile'"
                    :id="section.type === 'profile' ? 'program' : undefined"
                    class="grid gap-8 px-6 py-8 sm:px-8 lg:grid-cols-[1fr_0.9fr] lg:px-10"
                >
                    <div class="space-y-5">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[color:var(--brand-secondary)]">
                            Profil
                        </p>
                        <h2 class="font-serif text-3xl">
                            {{ renderSectionTitle(section) }}
                        </h2>
                        <p class="text-base leading-7 text-slate-600">
                            {{ section.config.about }}
                        </p>
                        <div class="rounded-[1.5rem] bg-slate-50 px-5 py-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[color:var(--brand-secondary)]">
                                Visi
                            </p>
                            <p class="mt-3 text-sm leading-7 text-slate-700 sm:text-base">
                                {{ section.config.vision }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <div
                            v-if="(section.config.mission || []).length"
                            class="rounded-[1.5rem] bg-slate-950 px-5 py-5 text-white"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-white/60">
                                Misi
                            </p>
                            <ul class="mt-4 grid gap-3 text-sm leading-6 text-white/85">
                                <li
                                    v-for="mission in section.config.mission || []"
                                    :key="mission"
                                    class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3"
                                >
                                    {{ mission }}
                                </li>
                            </ul>
                        </div>
                        <div
                            v-for="highlight in section.config.highlights || []"
                            :key="highlight"
                            class="rounded-2xl bg-slate-50 px-4 py-4 text-sm leading-6 text-slate-700"
                        >
                            {{ highlight }}
                        </div>
                        <div class="rounded-[1.5rem] border border-slate-200 px-5 py-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[color:var(--brand-secondary)]">
                                Kontak Profil
                            </p>
                            <div class="mt-4 grid gap-2 text-sm leading-6 text-slate-700">
                                <p>{{ resolveSectionContact(section).address }}</p>
                                <p v-if="resolveSectionContact(section).phone">Telp: {{ resolveSectionContact(section).phone }}</p>
                                <p v-if="resolveSectionContact(section).email">Email: {{ resolveSectionContact(section).email }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="section.type === 'blog'"
                    class="grid gap-6 px-6 py-8 sm:px-8 lg:px-10"
                >
                    <div class="max-w-3xl space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[color:var(--brand-secondary)]">
                            Artikel
                        </p>
                        <h2 class="font-serif text-3xl">
                            {{ renderSectionTitle(section) }}
                        </h2>
                        <p class="text-base leading-7 text-slate-600">
                            {{ section.config.description }}
                        </p>
                    </div>

                    <div
                        v-if="(section.config.articles || []).length"
                        class="grid gap-4 lg:grid-cols-3"
                    >
                        <article
                            v-for="article in section.config.articles"
                            :key="article.slug"
                            class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-slate-50"
                        >
                            <div
                                v-if="article.featuredImageUrl"
                                class="h-44 bg-slate-200"
                            >
                                <img
                                    :src="article.featuredImageUrl"
                                    :alt="article.title"
                                    class="h-full w-full object-cover"
                                >
                            </div>
                            <div class="grid gap-3 px-5 py-5">
                                <p
                                    v-if="article.publishedAt"
                                    class="text-xs font-semibold uppercase tracking-[0.25em] text-[color:var(--brand-secondary)]"
                                >
                                    {{ article.publishedAt }}
                                </p>
                                <h3 class="font-serif text-2xl leading-snug text-slate-900">
                                    {{ article.title }}
                                </h3>
                                <p class="text-sm leading-6 text-slate-600">
                                    {{ article.excerpt }}
                                </p>
                                <a
                                    :href="article.href"
                                    class="text-sm font-semibold"
                                    :style="{ color: 'var(--brand-primary)' }"
                                >
                                    Baca selengkapnya
                                </a>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="rounded-[1.5rem] border border-dashed border-slate-300 px-5 py-5 text-sm leading-6 text-slate-600"
                    >
                        {{ section.config.emptyState }}
                    </div>
                </div>

                <div
                    v-else-if="section.type === 'gallery'"
                    class="grid gap-6 px-6 py-8 sm:px-8 lg:px-10"
                >
                    <div class="max-w-3xl space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[color:var(--brand-secondary)]">
                            Galeri
                        </p>
                        <h2 class="font-serif text-3xl">
                            {{ renderSectionTitle(section) }}
                        </h2>
                        <p class="text-base leading-7 text-slate-600">
                            {{ section.config.description }}
                        </p>
                    </div>

                    <div
                        v-if="(section.config.galleries || []).length"
                        class="grid gap-4 xl:grid-cols-3"
                    >
                        <article
                            v-for="gallery in section.config.galleries"
                            :key="gallery.slug"
                            class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-slate-50"
                        >
                            <div
                                v-if="gallery.coverImageUrl"
                                class="h-52 bg-slate-200"
                            >
                                <img
                                    :src="gallery.coverImageUrl"
                                    :alt="gallery.title"
                                    class="h-full w-full object-cover"
                                >
                            </div>
                            <div class="grid gap-4 px-5 py-5">
                                <div class="space-y-2">
                                    <p
                                        v-if="gallery.publishedAt"
                                        class="text-xs font-semibold uppercase tracking-[0.25em] text-[color:var(--brand-secondary)]"
                                    >
                                        {{ gallery.publishedAt }}
                                    </p>
                                    <h3 class="font-serif text-2xl leading-snug text-slate-900">
                                        {{ gallery.title }}
                                    </h3>
                                    <p class="text-sm leading-6 text-slate-600">
                                        {{ gallery.description }}
                                    </p>
                                </div>

                                <div
                                    v-if="(gallery.images || []).length"
                                    class="grid grid-cols-3 gap-2"
                                >
                                    <div
                                        v-for="(image, index) in gallery.images.slice(0, 3)"
                                        :key="`${gallery.slug}-${index}`"
                                        class="overflow-hidden rounded-2xl bg-slate-200"
                                    >
                                        <img
                                            :src="image.imageUrl"
                                            :alt="image.caption || gallery.title"
                                            class="h-20 w-full object-cover"
                                        >
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="rounded-[1.5rem] border border-dashed border-slate-300 px-5 py-5 text-sm leading-6 text-slate-600"
                    >
                        {{ section.config.emptyState }}
                    </div>
                </div>

                <div
                    v-else-if="section.type === 'faq'"
                    class="grid gap-6 px-6 py-8 sm:px-8 lg:px-10"
                >
                    <div class="max-w-3xl space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[color:var(--brand-secondary)]">
                            FAQ
                        </p>
                        <h2 class="font-serif text-3xl">
                            {{ renderSectionTitle(section) }}
                        </h2>
                        <p class="text-base leading-7 text-slate-600">
                            {{ section.config.description }}
                        </p>
                    </div>

                    <div
                        v-if="(section.config.items || []).length"
                        class="grid gap-4"
                    >
                        <article
                            v-for="item in section.config.items"
                            :key="item.question"
                            class="rounded-[1.5rem] border border-slate-200 bg-slate-50 px-5 py-5"
                        >
                            <h3 class="font-serif text-2xl leading-snug text-slate-900">
                                {{ item.question }}
                            </h3>
                            <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">
                                {{ item.answer }}
                            </p>
                        </article>
                    </div>

                    <div
                        v-else
                        class="rounded-[1.5rem] border border-dashed border-slate-300 px-5 py-5 text-sm leading-6 text-slate-600"
                    >
                        {{ section.config.emptyState }}
                    </div>
                </div>

                <div
                    v-else-if="section.type === 'testimonial'"
                    class="grid gap-6 px-6 py-8 sm:px-8 lg:grid-cols-[1.05fr_0.95fr] lg:px-10"
                >
                    <div class="space-y-4">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[color:var(--brand-secondary)]">
                            Sambutan
                        </p>
                        <h2 class="font-serif text-3xl">
                            {{ renderSectionTitle(section) }}
                        </h2>
                        <p class="text-base leading-7 text-slate-600">
                            {{ section.config.description }}
                        </p>

                        <blockquote class="rounded-[1.75rem] bg-slate-950 px-6 py-6 text-white shadow-[0_18px_50px_-35px_rgba(15,23,42,0.85)]">
                            <p class="font-serif text-2xl leading-relaxed">
                                “{{ section.config.quote }}”
                            </p>
                            <footer class="mt-5">
                                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-white/60">
                                    {{ section.config.leaderName }}
                                </p>
                                <p class="mt-2 text-sm text-white/80">
                                    {{ section.config.leaderRole }}
                                </p>
                            </footer>
                        </blockquote>
                    </div>

                    <div class="grid gap-4">
                        <div
                            v-if="section.config.leaderPhotoUrl"
                            class="overflow-hidden rounded-[1.75rem] bg-slate-100"
                        >
                            <img
                                :src="section.config.leaderPhotoUrl"
                                :alt="section.config.leaderName || 'Pimpinan pesantren'"
                                class="h-72 w-full object-cover"
                            >
                        </div>

                        <div
                            v-if="(section.config.highlights || []).length"
                            class="grid gap-3"
                        >
                            <div
                                v-for="highlight in section.config.highlights"
                                :key="highlight"
                                class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm leading-6 text-slate-700"
                            >
                                {{ highlight }}
                            </div>
                        </div>

                        <a
                            :href="section.config.ctaHref || '#kontak'"
                            class="inline-flex w-fit rounded-full px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90"
                            :style="{ backgroundColor: 'var(--brand-primary)' }"
                        >
                            {{ section.config.ctaLabel || 'Hubungi Pesantren' }}
                        </a>
                    </div>
                </div>

                <div
                    v-else-if="section.type === 'ppdb_cta'"
                    class="flex flex-col gap-4 px-6 py-8 sm:px-8 lg:flex-row lg:items-end lg:justify-between lg:px-10"
                >
                    <div class="max-w-2xl space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[color:var(--brand-secondary)]">
                            PPDB
                        </p>
                        <h2 class="font-serif text-3xl">
                            {{ renderSectionTitle(section) }}
                        </h2>
                        <p class="text-base leading-7 text-slate-600">
                            {{ section.config.body }}
                        </p>
                        <p class="text-sm leading-6 text-slate-500">
                            {{ section.config.helperText }}
                        </p>
                    </div>

                    <a
                        :href="section.config.buttonHref || '/ppdb'"
                        class="inline-flex rounded-full px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90"
                        :style="{ backgroundColor: 'var(--brand-secondary)' }"
                    >
                        {{ section.config.buttonLabel || 'Buka Form PPDB' }}
                    </a>
                </div>

                <div v-else class="px-6 py-8 sm:px-8 lg:px-10">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[color:var(--brand-secondary)]">
                        {{ section.type }}
                    </p>
                    <h2 class="mt-3 font-serif text-3xl">
                        {{ renderSectionTitle(section) }}
                    </h2>
                </div>
            </section>
        </main>

        <footer id="kontak" class="border-t border-black/5 bg-slate-950 text-white">
            <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:px-8">
                <div class="space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-white/55">
                        Kontak
                    </p>
                    <h2 class="font-serif text-2xl">
                        {{ website.branding.name }}
                    </h2>
                    <p class="max-w-xl text-sm leading-7 text-white/70">
                        {{ websiteContact.address }}
                    </p>
                </div>

                <div class="grid gap-2 text-sm text-white/75">
                    <p v-if="websiteContact.phone">Telp: {{ websiteContact.phone }}</p>
                    <p v-if="websiteContact.email">Email: {{ websiteContact.email }}</p>
                    <p>Tenant ID: {{ tenant.id }}</p>
                </div>
            </div>
        </footer>
        </div>
        </div>
    </div>
</template>
