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

const renderSectionTitle = (section) => section.config?.title ?? section.type
const renderHeroDescription = (section) => section.config?.description || props.website.branding.tagline
const seo = props.website.seo ?? {}
const schemaMarkup = seo.schema ? JSON.stringify(seo.schema) : null
const isAlbaKidsExperience = props.tenant?.id === 'albakids'
const albaBrochurePages = Array.from({ length: 9 }, (_, index) => ({
    src: `/albakids-brochure/${index + 1}.jpg`,
    alt: `Brosur Alba Kids ${index + 1}`,
}))
const albaIntroPages = albaBrochurePages.slice(0, 6)
const albaOutroPages = albaBrochurePages.slice(6)
const admission = props.website.admission ?? { enabled: false, transfer: {}, programOptions: [], classOptions: [] }
const websiteContact = props.website.contact ?? { address: '', phone: '', email: '' }
const flash = usePage().props.flash ?? {}
const resolveSectionContact = (section) => section.config?.contact ?? websiteContact
const extractNumericValue = (value) => {
    const digits = String(value ?? '').replace(/[^\d]/g, '')

    return digits === '' ? '' : Number.parseInt(digits, 10)
}
const defaultTransferAmount = extractNumericValue(admission.transfer?.nominal)

const form = useForm({
    nama_anak: '',
    nama_panggilan: '',
    nik_anak: '',
    tempat_lahir: '',
    tanggal_lahir: '',
    jenis_kelamin: '',
    child_order: '',
    kelas_tujuan: admission.classOptions?.[0] ?? '',
    program_diminati: admission.programOptions?.[0] ?? '',
    asal_sekolah: '',
    nama_ayah: '',
    nama_ibu: '',
    pekerjaan_ayah: '',
    pekerjaan_ibu: '',
    no_hp_ayah: '',
    no_hp_ibu: '',
    nama_wali: '',
    hubungan_wali: 'Ibu',
    no_hp_wali: '',
    email_wali: '',
    alamat: '',
    kota_domisili: '',
    nama_pengirim_transfer: '',
    bank_pengirim: admission.transfer?.bankName ?? '',
    nominal_transfer: defaultTransferAmount,
    tanggal_transfer: '',
    sumber_informasi: '',
    catatan_tambahan: '',
    bukti_transfer: null,
})

const submitAdmission = () => {
    const guardianName = form.nama_ibu || form.nama_ayah || form.nama_wali
    const guardianPhone = form.no_hp_ibu || form.no_hp_ayah || form.no_hp_wali
    const notes = [
        form.child_order ? `Anak ke: ${form.child_order}` : null,
        form.pekerjaan_ayah ? `Pekerjaan Ayah: ${form.pekerjaan_ayah}` : null,
        form.pekerjaan_ibu ? `Pekerjaan Ibu: ${form.pekerjaan_ibu}` : null,
        form.no_hp_ayah ? `No. WhatsApp Ayah: ${form.no_hp_ayah}` : null,
        form.no_hp_ibu ? `No. WhatsApp Ibu: ${form.no_hp_ibu}` : null,
        form.catatan_tambahan || null,
    ].filter(Boolean).join('\n')

    form.transform((data) => ({
        ...data,
        nama_wali: guardianName,
        hubungan_wali: data.hubungan_wali || (data.nama_ibu ? 'Ibu' : 'Ayah'),
        no_hp_wali: guardianPhone,
        bank_pengirim: data.bank_pengirim || admission.transfer?.bankName || '',
        nominal_transfer: extractNumericValue(data.nominal_transfer) || defaultTransferAmount || '',
        catatan_tambahan: notes,
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
            v-if="isAlbaKidsExperience"
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
                                            Isi data calon siswa, data orang tua, lalu unggah bukti transfer pendaftaran. Tampilan form ini mengikuti brosur Alba Kids yang Ustadz kirim.
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
                                                <span>Nama Panggilan</span>
                                                <input v-model="form.nama_panggilan" type="text" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Tempat Lahir*</span>
                                                <input v-model="form.tempat_lahir" type="text" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
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
                                                <span>Kelas Tujuan*</span>
                                                <select v-model="form.kelas_tujuan" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                                    <option v-for="option in admission.classOptions" :key="option" :value="option">{{ option }}</option>
                                                </select>
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Program*</span>
                                                <select v-model="form.program_diminati" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                                    <option v-for="option in admission.programOptions" :key="option" :value="option">{{ option }}</option>
                                                </select>
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

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Nama Pengirim Transfer*</span>
                                                <input v-model="form.nama_pengirim_transfer" type="text" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Tanggal Transfer*</span>
                                                <input v-model="form.tanggal_transfer" type="date" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Bank Pengirim*</span>
                                                <input v-model="form.bank_pengirim" type="text" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            </label>

                                            <label class="grid gap-2 text-sm font-semibold text-[#315c4f]">
                                                <span>Nominal Transfer*</span>
                                                <input v-model="form.nominal_transfer" type="number" min="0" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-normal text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
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

        <div
            v-else
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
                    :href="admission.enabled ? '#form-pendaftaran' : '/ppdb'"
                    class="rounded-full px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                    :style="{ backgroundColor: 'var(--brand-primary)' }"
                >
                    {{ admission.enabled ? 'Daftar Alba Kids' : 'PPDB Online' }}
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

            <section
                v-if="admission.enabled"
                id="form-pendaftaran"
                class="overflow-hidden rounded-[2rem] border border-black/5 bg-white shadow-[0_20px_60px_-35px_rgba(15,23,42,0.25)]"
            >
                <div class="grid gap-8 px-6 py-8 sm:px-8 lg:grid-cols-[0.92fr_1.08fr] lg:px-10 lg:py-10">
                    <div class="space-y-5">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[color:var(--brand-secondary)]">
                            Form Pendaftaran
                        </p>
                        <div class="space-y-3">
                            <h2 class="font-serif text-3xl sm:text-4xl">
                                {{ admission.headline }}
                            </h2>
                            <p class="text-base leading-7 text-slate-600">
                                {{ admission.description }}
                            </p>
                        </div>

                        <div class="rounded-[1.5rem] bg-slate-50 px-5 py-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[color:var(--brand-secondary)]">
                                Informasi Transfer
                            </p>
                            <div class="mt-4 grid gap-2 text-sm leading-6 text-slate-700">
                                <p><strong>Bank:</strong> {{ admission.transfer.bankName }}</p>
                                <p><strong>No. Rekening:</strong> {{ admission.transfer.accountNumber }}</p>
                                <p><strong>Atas Nama:</strong> {{ admission.transfer.accountName }}</p>
                                <p v-if="admission.transfer.nominal"><strong>Nominal:</strong> {{ admission.transfer.nominal }}</p>
                                <p>{{ admission.transfer.instructions }}</p>
                            </div>
                        </div>

                        <div class="rounded-[1.5rem] bg-slate-950 px-5 py-5 text-white">
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-white/60">
                                Setelah Submit
                            </p>
                            <ul class="mt-4 grid gap-3 text-sm leading-6 text-white/85">
                                <li class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    Sistem mengirim ucapan terima kasih ke WhatsApp wali.
                                </li>
                                <li class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    Admin Alba Kids langsung menerima notifikasi pendaftar baru.
                                </li>
                                <li class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    Bukti transfer masuk ke antrean verifikasi admin.
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50/70 px-5 py-5 sm:px-6">
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

                        <form class="grid gap-4" @submit.prevent="submitAdmission">
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Nama lengkap anak *</span>
                                    <input v-model="form.nama_anak" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Nama panggilan</span>
                                    <input v-model="form.nama_panggilan" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Tempat lahir *</span>
                                    <input v-model="form.tempat_lahir" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Tanggal lahir *</span>
                                    <input v-model="form.tanggal_lahir" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Jenis kelamin *</span>
                                    <select v-model="form.jenis_kelamin" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                        <option value="">Pilih</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Kelas tujuan *</span>
                                    <select v-model="form.kelas_tujuan" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                        <option v-for="option in admission.classOptions" :key="option" :value="option">{{ option }}</option>
                                    </select>
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Program *</span>
                                    <select v-model="form.program_diminati" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                        <option v-for="option in admission.programOptions" :key="option" :value="option">{{ option }}</option>
                                    </select>
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Asal sekolah</span>
                                    <input v-model="form.asal_sekolah" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Nama ayah</span>
                                    <input v-model="form.nama_ayah" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Nama ibu *</span>
                                    <input v-model="form.nama_ibu" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Nama wali/pengisi form *</span>
                                    <input v-model="form.nama_wali" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Hubungan dengan anak *</span>
                                    <input v-model="form.hubungan_wali" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>No. WhatsApp wali *</span>
                                    <input v-model="form.no_hp_wali" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Email wali</span>
                                    <input v-model="form.email_wali" type="email" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                            </div>

                            <label class="grid gap-2 text-sm text-slate-700">
                                <span>Alamat domisili *</span>
                                <textarea v-model="form.alamat" rows="3" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]"></textarea>
                            </label>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Kota domisili</span>
                                    <input v-model="form.kota_domisili" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Sumber informasi</span>
                                    <input v-model="form.sumber_informasi" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Nama pengirim transfer *</span>
                                    <input v-model="form.nama_pengirim_transfer" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Bank pengirim *</span>
                                    <input v-model="form.bank_pengirim" type="text" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Nominal transfer *</span>
                                    <input v-model="form.nominal_transfer" type="number" min="0" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                                <label class="grid gap-2 text-sm text-slate-700">
                                    <span>Tanggal transfer *</span>
                                    <input v-model="form.tanggal_transfer" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]">
                                </label>
                            </div>

                            <label class="grid gap-2 text-sm text-slate-700">
                                <span>Bukti transfer *</span>
                                <input type="file" accept=".jpg,.jpeg,.png,.pdf,.webp" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm" @input="form.bukti_transfer = $event.target.files[0]">
                            </label>

                            <label class="grid gap-2 text-sm text-slate-700">
                                <span>Catatan tambahan</span>
                                <textarea v-model="form.catatan_tambahan" rows="3" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-[color:var(--brand-primary)]"></textarea>
                            </label>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center rounded-full px-6 py-3 text-sm font-semibold text-white transition disabled:opacity-60"
                                :style="{ backgroundColor: 'var(--brand-primary)' }"
                            >
                                {{ form.processing ? 'Mengirim pendaftaran...' : 'Kirim Pendaftaran Alba Kids' }}
                            </button>
                        </form>
                    </div>
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
