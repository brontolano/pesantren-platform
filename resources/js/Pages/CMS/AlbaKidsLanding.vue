<script setup>
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
    blog: {
        type: Object,
        default: () => ({
            indexUrl: '/#blog',
            hasPublishedArticles: false,
        }),
    },
})

const seo = props.website.seo ?? {}
const schemaMarkup = seo.schema ? JSON.stringify(seo.schema) : null
const albaBrochurePages = Array.from({ length: 9 }, (_, index) => ({
    src: `/albakids-brochure/${index + 1}.jpg`,
    alt: `Brosur Alba Kids ${index + 1}`,
}))
const albaIntroPages = albaBrochurePages.slice(0, 6)
const albaOutroPages = albaBrochurePages.slice(6)
const admission = props.website.admission ?? { enabled: false, transfer: {}, programOptions: [], classOptions: [] }
const websiteContact = props.website.contact ?? { address: '', phone: '', email: '' }
const flash = usePage().props.flash ?? {}
const extractNumericValue = (value) => {
    const digits = String(value ?? '').replace(/[^\d]/g, '')

    return digits === '' ? '' : Number.parseInt(digits, 10)
}
const defaultTransferAmount = extractNumericValue(admission.transfer?.nominal)

const form = useForm({
    nama_anak: '',
    tempat_lahir: '',
    tanggal_lahir: '',
    jenis_kelamin: '',
    child_order: '',
    nama_ayah: '',
    nama_ibu: '',
    pekerjaan_ayah: '',
    pekerjaan_ibu: '',
    no_hp_ayah: '',
    no_hp_ibu: '',
    alamat: '',
    bukti_transfer: null,
})

const submitAdmission = () => {
    form.transform((data) => ({
        ...data,
        nama_wali: data.nama_ibu || data.nama_ayah,
        hubungan_wali: 'Ibu',
        no_hp_wali: data.no_hp_ibu || data.no_hp_ayah,
        kelas_tujuan: admission.classOptions?.[0] ?? 'Kelompok Bermain',
        program_diminati: admission.programOptions?.[0] ?? 'Preschool',
        nama_pengirim_transfer: data.nama_ibu || data.nama_ayah,
        bank_pengirim: admission.transfer?.bankName || '',
        nominal_transfer: defaultTransferAmount || '',
        tanggal_transfer: new Date().toISOString().slice(0, 10),
    })).post(admission.endpoint, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('bukti_transfer'),
    })
}
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
            class="min-h-screen bg-[#f9fcff] text-slate-900"
            :style="{
                '--brand-primary': website.branding.primaryColor,
                '--brand-secondary': website.branding.secondaryColor,
                '--brand-font-family': website.branding.fontFamily || 'ui-sans-serif, system-ui, sans-serif',
            }"
        >
            <div class="sticky top-0 z-30 border-b border-sky-100 bg-white/90 backdrop-blur">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <img
                            v-if="website.branding.logoUrl"
                            :src="website.branding.logoUrl"
                            :alt="website.branding.name"
                            class="h-11 w-11 rounded-full bg-white object-contain p-1 shadow-sm ring-2 ring-[#ffd46b]"
                        >
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-500">Alba Kids</p>
                            <h1 class="text-xl font-black text-[#df6b2d]">{{ website.branding.name }}</h1>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="rounded-full bg-[#e4f3ff] px-3 py-1 font-semibold text-sky-700">
                            {{ admission.academicYear }}
                        </span>
                        <a
                            href="#form-pendaftaran"
                            class="inline-flex rounded-full px-4 py-2 font-semibold text-white shadow-sm transition hover:-translate-y-0.5"
                            :style="{ backgroundColor: 'var(--brand-primary)' }"
                        >
                            Isi Form Pendaftaran
                        </a>
                    </div>
                </div>
            </div>

            <main class="pb-16">
                <section
                    v-for="page in albaIntroPages"
                    :key="page.src"
                    class="border-b border-sky-100 bg-white"
                >
                    <img :src="page.src" :alt="page.alt" class="mx-auto w-full max-w-[1440px] object-cover">
                </section>

                <section id="form-pendaftaran" class="bg-[linear-gradient(180deg,#e8f6ff_0%,#ffffff_100%)] py-16 lg:py-24">
                    <div class="mx-auto max-w-5xl px-5 sm:px-8 lg:px-10">
                        <div class="overflow-hidden rounded-[2rem] border border-sky-100 bg-white shadow-[0_25px_70px_-35px_rgba(34,87,122,0.35)]">
                            <div class="grid gap-8 px-6 py-8 lg:grid-cols-[0.9fr_1.1fr] lg:px-10 lg:py-12">
                                <div class="space-y-6">
                                    <div class="space-y-3">
                                        <span class="inline-flex rounded-full bg-[#fff1cc] px-4 py-1 text-sm font-bold text-[#df6b2d]">
                                            Pendaftaran {{ admission.academicYear }}
                                        </span>
                                        <h2 class="text-3xl font-black leading-tight text-sky-600 sm:text-4xl">
                                            Formulir Pendaftaran Alba Kids
                                        </h2>
                                        <p class="text-base leading-7 text-slate-600">
                                            Isi form singkat berikut sesuai data yang diminta di brosur, lalu unggah bukti transfer pendaftaran.
                                        </p>
                                    </div>

                                    <div class="rounded-[1.75rem] border-l-4 border-[#df6b2d] bg-[#fde5d7] px-5 py-5 text-slate-700">
                                        <h3 class="text-lg font-black text-[#315c4f]">Instruksi Pembayaran</h3>
                                        <p class="mt-3 text-2xl font-black text-[#df6b2d]">
                                            {{ admission.transfer.nominal || 'Rp 1.885.000' }}
                                        </p>
                                        <div class="mt-4 rounded-2xl bg-white/70 px-4 py-4 text-sm leading-7">
                                            <p class="font-semibold">{{ admission.transfer.bankName || 'Bank Mandiri' }}</p>
                                            <p>a.n. {{ admission.transfer.accountName || 'Inna Siti Nurhasanah' }}</p>
                                            <p>{{ admission.transfer.accountNumber || '1300005641850' }}</p>
                                        </div>
                                        <p class="mt-4 text-sm italic text-slate-500">
                                            Setelah melakukan transfer, mohon upload bukti pembayaran pada kolom di bawah ini.
                                        </p>
                                    </div>

                                    <div class="rounded-[1.75rem] bg-[#e8f6ff] px-5 py-5">
                                        <h3 class="text-lg font-black text-sky-600">Informasi Singkat</h3>
                                        <ul class="mt-4 grid gap-3 text-sm leading-6 text-slate-700">
                                            <li class="rounded-2xl bg-white px-4 py-3 shadow-sm">Usia anak: 3 sampai 5 tahun</li>
                                            <li class="rounded-2xl bg-white px-4 py-3 shadow-sm">Jadwal sekolah: Selasa dan Sabtu, pukul 09.00 sampai 11.00</li>
                                            <li class="rounded-2xl bg-white px-4 py-3 shadow-sm">Admin pendaftaran: {{ websiteContact.phone || '+62 822 6090 1399' }}</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="rounded-[1.75rem] bg-[#f9fcff] px-5 py-5 sm:px-6">
                                    <div
                                        v-if="flash.success"
                                        class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                                    >
                                        {{ flash.success }}
                                    </div>

                                    <div
                                        v-if="Object.keys(form.errors).length"
                                        class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
                                    >
                                        Mohon periksa kembali field yang masih perlu dilengkapi.
                                    </div>

                                    <form class="space-y-6" @submit.prevent="submitAdmission">
                                        <div class="grid gap-5 md:grid-cols-2">
                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f] md:col-span-2">
                                                <span>NAMA LENGKAP (Wajib kapital/huruf besar)*</span>
                                                <input v-model="form.nama_anak" type="text" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Jenis Kelamin*</span>
                                                <select v-model="form.jenis_kelamin" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                                    <option value="">Pilih Jenis Kelamin</option>
                                                    <option value="L">Laki-laki</option>
                                                    <option value="P">Perempuan</option>
                                                </select>
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Tempat, tanggal lahir*</span>
                                                <input v-model="form.tempat_lahir" type="text" required placeholder="Tempat lahir" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Tanggal Lahir*</span>
                                                <input v-model="form.tanggal_lahir" type="date" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Anak ke-*</span>
                                                <input v-model="form.child_order" type="text" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Nama Ayah (Lengkap)*</span>
                                                <input v-model="form.nama_ayah" type="text" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Nama Ibu (Lengkap)*</span>
                                                <input v-model="form.nama_ibu" type="text" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Pekerjaan Ayah*</span>
                                                <input v-model="form.pekerjaan_ayah" type="text" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Pekerjaan Ibu*</span>
                                                <input v-model="form.pekerjaan_ibu" type="text" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>No WhatsApp Ayah*</span>
                                                <input v-model="form.no_hp_ayah" type="tel" required placeholder="628xxxxxxxxx" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>No WhatsApp Ibu*</span>
                                                <input v-model="form.no_hp_ibu" type="tel" required placeholder="628xxxxxxxxx" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f] md:col-span-2">
                                                <span>Alamat Lengkap (Jalan, Desa/Kelurahan, Kecamatan, Kabupaten/Kota, Provinsi)*</span>
                                                <textarea v-model="form.alamat" rows="3" required class="w-full resize-none rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"></textarea>
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f] md:col-span-2">
                                                <span>Upload Bukti Transfer Pendaftaran*</span>
                                                <input type="file" accept=".jpg,.jpeg,.png,.pdf,.webp" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100" @input="form.bukti_transfer = $event.target.files[0]">
                                            </label>
                                        </div>

                                        <button
                                            type="submit"
                                            :disabled="form.processing"
                                            class="inline-flex w-full items-center justify-center rounded-xl py-4 text-base font-bold text-white transition hover:-translate-y-0.5 disabled:opacity-60"
                                            :style="{ background: 'linear-gradient(90deg, var(--brand-primary), #ffc75f)' }"
                                        >
                                            {{ form.processing ? 'Mengirim pendaftaran...' : 'Kirim Pendaftaran' }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-for="page in albaOutroPages"
                    :key="page.src"
                    class="border-b border-sky-100 bg-white"
                >
                    <img :src="page.src" :alt="page.alt" class="mx-auto w-full max-w-[1440px] object-cover">
                </section>
            </main>
        </div>
    </div>
</template>
