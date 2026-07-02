<script setup>
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'

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
    comments: {
        type: Array,
        default: () => [],
    },
    comment: {
        type: Object,
        default: () => ({ isLoggedIn: false, authorName: null, storeUrl: '', loginUrl: '/jemaah/login' }),
    },
})

const schemaMarkup = props.seo.schema ? JSON.stringify(props.seo.schema) : null

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success ?? null)

const form = useForm({ body: '' })
const submitComment = () => {
    form.post(props.comment.storeUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    })
}
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
                    <!--
                      contentHtml sudah DISANITASI di server (Symfony HtmlSanitizer:
                      elemen aman + iframe HANYA dari youtube-nocookie/vimeo). Aman
                      untuk v-html. Fallback: konten mentah (escaped) bila kosong.
                    -->
                    <div
                        v-if="article.contentHtml"
                        class="prose prose-slate max-w-none text-base leading-8"
                        v-html="article.contentHtml"
                    ></div>
                    <div
                        v-else
                        class="prose prose-slate max-w-none whitespace-pre-line text-base leading-8"
                    >
                        {{ article.content }}
                    </div>
                </div>
            </article>

            <!-- KOMENTAR (1-aplikasi, Fase 2c) -->
            <section class="rounded-[2rem] border border-black/5 bg-white px-6 py-7 shadow-[0_20px_60px_-35px_rgba(15,23,42,0.2)] sm:px-8 lg:px-10">
                <h3 class="font-serif text-2xl">
                    Komentar
                    <span class="text-base font-normal text-slate-400">({{ comments.length }})</span>
                </h3>

                <p
                    v-if="flashSuccess"
                    class="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
                >
                    {{ flashSuccess }}
                </p>

                <!-- Daftar komentar (approved) — body di-escape otomatis ({{ }}) -->
                <ul v-if="comments.length" class="mt-6 space-y-5">
                    <li
                        v-for="c in comments"
                        :key="c.id"
                        class="border-b border-black/5 pb-5 last:border-0 last:pb-0"
                    >
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="font-semibold text-slate-800">{{ c.author }}</span>
                            <span v-if="c.createdAt" class="text-xs text-slate-400">{{ c.createdAt }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-line text-[15px] leading-7 text-slate-600">{{ c.body }}</p>
                    </li>
                </ul>
                <p v-else class="mt-6 text-sm text-slate-500">
                    Belum ada komentar. Jadilah yang pertama berbagi tanggapan yang baik.
                </p>

                <!-- Form (hanya untuk yang sudah login) -->
                <form v-if="comment.isLoggedIn" class="mt-7 space-y-3" @submit.prevent="submitComment">
                    <label for="komentar-body" class="block text-sm font-medium text-slate-700">
                        Tulis komentar sebagai <span class="font-semibold">{{ comment.authorName }}</span>
                    </label>
                    <textarea
                        id="komentar-body"
                        v-model="form.body"
                        rows="3"
                        maxlength="2000"
                        required
                        placeholder="Tulis tanggapan yang santun…"
                        class="block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[color:var(--brand-primary)] focus:ring-2 focus:ring-[color:var(--brand-primary)]/15"
                    />
                    <p v-if="form.errors.body" class="text-sm text-red-600">{{ form.errors.body }}</p>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-full px-6 py-3 text-sm font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60"
                        :style="{ backgroundColor: 'var(--brand-primary)' }"
                    >
                        {{ form.processing ? 'Mengirim…' : 'Kirim Komentar' }}
                    </button>
                </form>
                <p v-else class="mt-7 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-600">
                    <a :href="comment.loginUrl" class="font-semibold" :style="{ color: 'var(--brand-primary)' }">Masuk</a>
                    untuk ikut berkomentar. Pengunjung tanpa akun dapat membaca komentar yang sudah tayang.
                </p>
            </section>
        </main>
        </div>
    </div>
</template>
