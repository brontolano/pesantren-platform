<script setup>
import { Link, usePage } from '@inertiajs/vue3'

defineProps({
    title: {
        type: String,
        default: 'Portal Wali',
    },
})

const page = usePage()
</script>

<template>
    <div class="min-h-screen bg-[linear-gradient(180deg,#f4efe6_0%,#fbf8f2_40%,#ffffff_100%)] text-slate-900">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col">
            <header class="sticky top-0 z-10 border-b border-black/5 bg-white/90 px-4 py-4 backdrop-blur">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-emerald-700">
                            Portal Wali
                        </p>
                        <h1 class="mt-1 font-serif text-2xl">
                            {{ title }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ page.props.wali?.name || 'Akun Wali' }}
                        </p>
                    </div>
                    <div class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        Mobile First
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-5">
                <slot />
            </main>

            <footer class="sticky bottom-0 border-t border-black/5 bg-white/95 px-4 py-3 backdrop-blur">
                <nav class="grid grid-cols-4 gap-2 text-center text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                    <Link
                        v-for="item in page.props.navigation || []"
                        :key="item.label"
                        :href="item.href"
                        class="rounded-2xl px-2 py-2 transition"
                        :class="item.active ? 'bg-emerald-50 text-emerald-700' : 'text-slate-500'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </footer>
        </div>
    </div>
</template>
