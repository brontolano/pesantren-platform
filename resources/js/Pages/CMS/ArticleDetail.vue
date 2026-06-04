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
    article: {
        type: Object,
        required: true,
    },
    seo: {
        type: Object,
        required: true,
    },
    homeUrl: {
        type: String,
        required: true,
    },
})

const schemaMarkup = props.seo.schema ? JSON.stringify(props.seo.schema) : null
</script>

<template>
    <div
        class="min-h-screen bg-[linear-gradient(180deg,#fcfbf7_0%,#fffefb_45%,#ffffff_100%)] text-slate-900"
        :style="{
            '--brand-primary': website.branding.primaryColor,
            '--brand-secondary': website.branding.secondaryColor,
            '--brand-font-family': website.branding.fontFamily || 'ui-sans-serif, system-ui, sans-serif',
        }"
    >
        <div :style="{ fontFamily: 'var(--brand-font-family)' }">
        <Head>
            <title>{{ seo.title }}</title>
            <meta head-key="description" name="description" :content="seo.description">
            <meta head-key="robots" name="robots" :content="seo.robots || 'index,follow'">
            <link head-key="canonical" rel="canonical" :href="seo.canonicalUrl">
            <meta head-key="og:title" property="og:title" :content="seo.title">
            <meta head-key="og:description" property="og:description" :content="seo.description">
            <meta head-key="og:type" property="og:type" content="article">
            <meta head-key="og:url" property="og:url" :content="seo.canonicalUrl">
            <meta head-key="og:site_name" property="og:site_name" :content="seo.siteName">
            <meta v-if="seo.imageUrl" head-key="og:image" property="og:image" :content="seo.imageUrl">
            <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">
            <meta head-key="twitter:title" name="twitter:title" :content="seo.title">
            <meta head-key="twitter:description" name="twitter:description" :content="seo.description">
            <meta v-if="seo.imageUrl" head-key="twitter:image" name="twitter:image" :content="seo.imageUrl">
            <script
                v-if="schemaMarkup"
                head-key="schema"
                type="application/ld+json"
                v-text="schemaMarkup"
            />
        </Head>

        <header class="border-b border-black/5 bg-white/85 backdrop-blur">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[color:var(--brand-secondary)]">
                        Artikel Pesantren
                    </p>
                    <h1 class="font-serif text-2xl font-semibold">
                        {{ website.branding.name }}
                    </h1>
                </div>

                <a
                    :href="homeUrl"
                    class="text-sm font-semibold"
                    :style="{ color: 'var(--brand-primary)' }"
                >
                    Kembali ke beranda
                </a>
            </div>
        </header>

        <main class="mx-auto grid max-w-4xl gap-6 px-4 py-8 sm:px-6 lg:px-8">
            <article class="overflow-hidden rounded-[2rem] border border-black/5 bg-white shadow-[0_20px_60px_-35px_rgba(15,23,42,0.2)]">
                <div
                    v-if="article.featuredImageUrl"
                    class="h-64 bg-slate-200 sm:h-80"
                >
                    <img
                        :src="article.featuredImageUrl"
                        :alt="article.title"
                        class="h-full w-full object-cover"
                    >
                </div>

                <div class="grid gap-5 px-6 py-7 sm:px-8 lg:px-10">
                    <p
                        v-if="article.publishedAt"
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-[color:var(--brand-secondary)]"
                    >
                        {{ article.publishedAt }}
                    </p>
                    <h2 class="font-serif text-4xl leading-tight">
                        {{ article.title }}
                    </h2>
                    <p
                        v-if="article.excerpt"
                        class="text-lg leading-8 text-slate-600"
                    >
                        {{ article.excerpt }}
                    </p>
                    <div class="prose prose-slate max-w-none whitespace-pre-line text-base leading-8">
                        {{ article.content }}
                    </div>
                </div>
            </article>
        </main>
        </div>
    </div>
</template>
