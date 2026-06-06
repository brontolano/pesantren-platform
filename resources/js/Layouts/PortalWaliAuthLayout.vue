<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const branding = computed(() => page.props.branding ?? {})

const rootStyle = computed(() => ({
    '--wali-primary': branding.value.primaryColor ?? '#0f172a',
    '--wali-secondary': branding.value.secondaryColor ?? '#d97706',
}))
</script>

<template>
    <Head>
        <meta name="theme-color" :content="branding.primaryColor ?? '#0f172a'">
    </Head>

    <div class="min-h-screen bg-stone-100 text-stone-900" :style="rootStyle">
        <div class="mx-auto flex min-h-screen max-w-md flex-col px-5 py-6">
            <Link href="/" class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-stone-600">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-[var(--wali-primary)] text-sm font-semibold text-white">
                    {{ (branding.shortName ?? 'PW').slice(0, 2).toUpperCase() }}
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-stone-900">{{ branding.name ?? 'Portal Wali' }}</span>
                    <span class="block truncate text-xs text-stone-500">{{ branding.tagline ?? 'Portal Wali Santri' }}</span>
                </span>
            </Link>

            <div class="mb-5 rounded-[2rem] bg-[linear-gradient(135deg,var(--wali-primary),var(--wali-secondary))] px-6 py-7 text-white shadow-lg shadow-stone-300/60">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-100/90">Portal Wali</p>
                <h1 class="mt-3 text-2xl font-semibold leading-tight">
                    Masuk, aktivasi, dan atur ulang akses wali dari HP.
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-white/80">
                    Gunakan nomor WhatsApp atau email wali yang terdaftar pada data santri aktif.
                </p>
            </div>

            <div class="flex-1 rounded-[2rem] bg-white p-6 shadow-sm shadow-stone-200/80">
                <slot />
            </div>
        </div>
    </div>
</template>
