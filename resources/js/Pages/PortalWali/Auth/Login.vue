<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import PortalWaliAuthLayout from '@/Layouts/PortalWaliAuthLayout.vue'

const form = useForm({
    identifier: '',
    password: '',
    remember: true,
})

const showPassword = ref(false)

const submit = () => {
    form.post('/wali/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Masuk Portal Wali" />

    <PortalWaliAuthLayout>
        <div>
            <h1 class="font-['Jost',sans-serif] text-[28px] font-bold leading-[1.15] tracking-tight text-[#111]">
                Selamat datang<br>kembali
            </h1>
            <p class="mt-2.5 text-[15px] leading-relaxed text-[#64748b]">
                Masuk untuk memantau ananda dan mengurus keperluan dengan pesantren.
            </p>

            <form class="mt-8 space-y-4" @submit.prevent="submit">
                <div class="space-y-[7px]">
                    <label for="identifier" class="font-['Jost',sans-serif] text-[13px] font-semibold text-[#1e293b]">
                        Nomor WhatsApp
                    </label>
                    <div class="flex items-center gap-2.5 rounded-[14px] bg-white px-4 py-3.5 shadow-[inset_0_0_0_1px_#efe7da] transition focus-within:shadow-[inset_0_0_0_2px_#059669]">
                        <svg class="h-5 w-5 shrink-0 text-[#94a3b8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <input
                            id="identifier"
                            v-model="form.identifier"
                            type="text"
                            name="identifier"
                            autocomplete="username"
                            required
                            placeholder="0812 3456 7890"
                            class="w-full border-0 bg-transparent p-0 text-[15px] text-[#111] outline-none placeholder:text-[#94a3b8] focus:ring-0"
                        >
                    </div>
                    <p v-if="form.errors.identifier" class="text-sm text-red-600">
                        {{ form.errors.identifier }}
                    </p>
                </div>

                <div class="space-y-[7px]">
                    <label for="password" class="font-['Jost',sans-serif] text-[13px] font-semibold text-[#1e293b]">
                        Kata sandi
                    </label>
                    <div class="flex items-center justify-between rounded-[14px] bg-white px-4 py-3.5 shadow-[inset_0_0_0_1px_#efe7da] transition focus-within:shadow-[inset_0_0_0_2px_#059669]">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            autocomplete="current-password"
                            required
                            placeholder="••••••••"
                            class="w-full border-0 bg-transparent p-0 text-[15px] text-[#111] outline-none placeholder:text-[#94a3b8] focus:ring-0"
                        >
                        <button
                            type="button"
                            class="ml-3 shrink-0 font-['Jost',sans-serif] text-xs font-semibold text-emerald-700"
                            @click="showPassword = !showPassword"
                        >
                            {{ showPassword ? 'Sembunyikan' : 'Tampilkan' }}
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="text-sm text-red-600">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div class="text-right">
                    <Link href="/wali/lupa-password" class="font-['Jost',sans-serif] text-[13px] font-semibold text-emerald-700">
                        Lupa kata sandi?
                    </Link>
                </div>

                <div class="mt-7 space-y-3.5">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="block w-full rounded-full bg-emerald-600 px-4 py-4 text-center font-['Jost',sans-serif] text-base font-semibold text-white shadow-[0_8px_20px_-12px_rgba(5,150,105,0.45)] transition active:scale-[0.99] disabled:opacity-60"
                    >
                        {{ form.processing ? 'Memproses…' : 'Masuk' }}
                    </button>

                    <div class="flex items-center gap-3.5 text-[#94a3b8]">
                        <span class="h-px flex-1 bg-[#e7d3a1]" />
                        <span class="text-xs">akun baru?</span>
                        <span class="h-px flex-1 bg-[#e7d3a1]" />
                    </div>

                    <Link
                        href="/wali/aktivasi"
                        class="block w-full rounded-full bg-white px-4 py-[15px] text-center font-['Jost',sans-serif] text-[15px] font-semibold text-emerald-700 shadow-[inset_0_0_0_1px_#efe7da] transition active:scale-[0.99]"
                    >
                        Aktifkan dengan kode dari pesantren
                    </Link>
                </div>
            </form>
        </div>
    </PortalWaliAuthLayout>
</template>
