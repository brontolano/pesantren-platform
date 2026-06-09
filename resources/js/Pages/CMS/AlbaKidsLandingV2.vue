<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'

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

/* ------------------------------------------------------------------ *
 * Kontrak data (dipertahankan 100% sama dengan AlbaKidsLanding.vue)
 * ------------------------------------------------------------------ */
const branding = computed(() => props.website.branding ?? {})
const seo = computed(() => props.website.seo ?? {})
const schemaMarkup = computed(() => (seo.value.schema ? JSON.stringify(seo.value.schema) : null))
const admission = computed(
    () => props.website.admission ?? { enabled: false, transfer: {}, programOptions: [], classOptions: [] },
)
const websiteContact = computed(() => props.website.contact ?? { address: '', phone: '', email: '' })
const flash = computed(() => usePage().props.flash ?? {})

const brandName = computed(() => branding.value.name || 'Albakids Preschool')
const brandTagline = computed(
    () => branding.value.tagline || 'Ruang tumbuh yang ceria, hangat, dan penuh imajinasi untuk langkah awal si kecil.',
)
const logoSrc = computed(() => branding.value.logoUrl || branding.value.logo || '/albakids/logo.png')
const academicYear = computed(() => admission.value.academicYear || '2026/2027')

/* ------------------------------------------------------------------ *
 * SEO — target pencarian lokal: preschool/PAUD Islam Montessori Bandung
 * ------------------------------------------------------------------ */
const SITE_URL = 'https://albakids.pesantren.online'
const seoTitle = computed(() => 'Albakids Preschool — Preschool & PAUD Islam Montessori di Bandung')
const seoDescription = computed(
    () => 'Albakids Preschool: PAUD & preschool Islam dengan pendekatan Montessori di Margaasih, Bandung. Belajar sambil bermain berlandaskan nilai tauhid & sunnah Nabi Muhammad SAW. Pendaftaran tahun ajaran 2026/2027 telah dibuka.',
)
const seoImage = computed(() => seo.value.imageUrl || `${SITE_URL}/albakids/gal/foto-1.jpg`)
const seoUrl = computed(() => seo.value.canonicalUrl || `${SITE_URL}/`)
const seoKeywords = 'preschool bandung, paud bandung, preschool islam bandung, paud islam bandung, montessori bandung, sekolah anak bandung, tk islam bandung, playgroup bandung, preschool margaasih, daycare bandung, albakids, al-basyariyah'
const localSchema = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': ['Preschool', 'EducationalOrganization', 'LocalBusiness'],
    name: brandName.value,
    alternateName: 'Albakids Al-Basyariyah Kinder Preschool',
    description: seoDescription.value,
    url: seoUrl.value,
    logo: `${SITE_URL}/albakids/logo.png`,
    image: seoImage.value,
    telephone: websiteContact.value.phone || '+62 822-6090-1399',
    priceRange: 'Rp',
    address: {
        '@type': 'PostalAddress',
        streetAddress: 'Pondok Pesantren Al-Basyariyah 2, Jl. Mahmud, Cigondewah Hilir',
        addressLocality: 'Margaasih',
        addressRegion: 'Jawa Barat',
        addressCountry: 'ID',
    },
    areaServed: [
        { '@type': 'City', name: 'Bandung' },
        { '@type': 'AdministrativeArea', name: 'Kabupaten Bandung' },
    ],
    sameAs: ['https://www.instagram.com/albakids_preschool.bdg/'],
    openingHoursSpecification: [
        { '@type': 'OpeningHoursSpecification', dayOfWeek: ['Tuesday', 'Saturday'], opens: '09:00', closes: '10:30' },
    ],
    audience: { '@type': 'EducationalAudience', audienceType: 'Anak usia 3-5 tahun' },
}))

/* Suntik JSON-LD structured data ke <head> (Inertia Head tidak merender <script>). */
onMounted(() => {
    const el = document.createElement('script')
    el.type = 'application/ld+json'
    el.setAttribute('data-seo', 'albakids-localbusiness')
    el.textContent = localSchema.value
    document.head.appendChild(el)
})

/* Galeri foto asli Albakids (dari folder Drive klien) */
const albaBrochurePages = Array.from({ length: 12 }, (_, index) => ({
    src: `/albakids/gal/foto-${index + 1}.jpg`,
    alt: `Dokumentasi kegiatan Albakids ${index + 1}`,
}))

const handleImageError = (event) => {
    event.target.style.display = 'none'
    const wrapper = event.target.closest('[data-gallery-item]')
    if (wrapper) {
        wrapper.classList.add('alba-gallery-empty')
    }
}

/* ------------------------------------------------------------------ *
 * Program — dari admission.programOptions bila tersedia, else default
 * ------------------------------------------------------------------ */
const programMeta = {
    'Playgroup': { age: 'Usia 2 - 3 tahun', desc: 'Bermain sambil mengenal warna, suara, dan teman baru dalam suasana hangat.' },
    'Kelompok Bermain': { age: 'Usia 2 - 3 tahun', desc: 'Bermain sambil mengenal warna, suara, dan teman baru dalam suasana hangat.' },
    'TK A': { age: 'Usia 4 - 5 tahun', desc: 'Melatih kemandirian, motorik halus, dan rasa ingin tahu lewat aktivitas seru.' },
    'TK B': { age: 'Usia 5 - 6 tahun', desc: 'Menyiapkan kesiapan sekolah dengan literasi awal, numerasi, dan kepercayaan diri.' },
}
const programIcons = ['blocks', 'crayon', 'rocket']
const programs = computed(() => {
    const source = Array.isArray(admission.value.programOptions) && admission.value.programOptions.length
        ? admission.value.programOptions
        : ['Kelompok Bermain', 'TK A', 'TK B']

    return source.map((name, index) => ({
        name,
        age: programMeta[name]?.age ?? 'Usia dini',
        desc: programMeta[name]?.desc ?? 'Belajar dengan ceria, aman, dan penuh stimulasi positif setiap hari.',
        icon: programIcons[index % programIcons.length],
    }))
})

/* Konten orisinal */
const advantages = [
    { icon: 'sparkles', text: 'Menggunakan metode pendekatan Islamic Montessori-Inspired.' },
    { icon: 'palette', text: 'Program cooking class di Pabrik Albakery.' },
    { icon: 'users', text: 'Program olahraga berkeliling di area pesantren.' },
    { icon: 'heart', text: 'Program outing class.' },
    { icon: 'shield', text: 'Waktu belajar 2x seminggu mulai pukul 09.00 - 10.30 agar tidak memberatkan anak usia preschool.' },
    { icon: 'chat', text: 'Mengenalkan anak dengan lingkungan pesantren.' },
    { icon: 'sparkles', text: 'Lokasi strategis, 5 menit dari Gerbang Tol Margaasih.' },
]

const testimonials = [
    {
        quote:
            'Alhamdulillah selama 2 tahun sekolah di Albakids, anak saya jadi tidak pemalu lagi. Sudah mulai fokus dalam hal apapun. Pembelajarannya pun seru sehingga anak-anak tidak bosen. Apalagi miss-miss-nya yang selalu ceria dan sabar dalam menghadapi anak-anak. Pokoknya recommended banget buat mommy-mommy yang mau masukin anaknya ke Albakids 😍',
        name: 'Mom Alwafi',
        role: 'Wali Murid Albakids',
        initial: 'A',
        color: '#29ABE2',
    },
    {
        quote:
            'Alhamdulillah kedua anak saya sekolah di sini 🥰. Sangat merekomendasikan Albakids ini! Fasilitas belajarnya nyaman, bersih, dan aman untuk anak-anak. Metode belajarnya sangat seru sampai anak saya tidak pernah absen dan selalu semangat setiap pagi untuk berangkat sekolah. Lingkungannya sangat mendukung untuk tumbuh kembang anak secara positif karena berada di dalam Pondok Pesantren Al-Basyariyah 🥰✨',
        name: 'Mom Aira',
        role: 'Wali Murid Albakids',
        initial: 'A',
        color: '#EC008C',
    },
    {
        quote:
            'Alhamdulillah pengalaman yang luar biasa bagi kami, terutama anak kami belajar di Albakids. Yang mulanya anak kami sulit bergaul dengan teman sebayanya, alhamdulillah sekarang menjadi lebih mudah bergaul. Selama pembelajaran bersama Albakids, anak kami merasa senang dan bahagia karena metode yang digunakan sangat menyenangkan. Anak kami pun belajar agama dan menerapkannya secara bertahap sehingga mudah menyerap semua yang diajarkan para guru Albakids. Terima kasih Albakids telah mengajarkan karakter dan ilmu yang jauh lebih baik dari sebelumnya.',
        name: 'Mom Akash',
        role: 'Wali Murid Albakids',
        initial: 'A',
        color: '#F5A623',
    },
]

const trustBadges = ['Lead teacher berpengalaman', 'Lingkungan islami', 'Kegiatan stimulasi terprogram']

/* Program Kami — poin kurikulum (instruksi klien) */
const curriculum = [
    'Penerapan pemahaman tauhid',
    'Penerapan Sunnah Nabi Muhammad SAW di kehidupan sehari-hari',
    'Kegiatan bermain kreatif untuk menstimulasi perkembangan sensorik, motorik halus dan motorik kasar pada anak',
    'Basic English',
    'Basic Arabic',
    'Hafalan surat pendek, sholawat & doa sehari-hari',
    'Pengenalan membaca dan berhitung dasar',
]

/* Rincian biaya pendaftaran (instruksi klien — total 1.785.000) */
const feeItems = [
    { label: 'Administrasi Pendaftaran', amount: 'Rp 250.000' },
    { label: 'Infaq Sarana Prasarana', amount: 'Rp 500.000' },
    { label: 'SPP Bulanan', amount: 'Rp 200.000' },
    { label: '2 Set Seragam', amount: 'Rp 300.000' },
    { label: 'Media Pembelajaran untuk 1 Tahun', amount: 'Rp 400.000' },
    { label: 'Raport untuk 2 Semester', amount: 'Rp 135.000' },
    { label: 'Program Outing Class / tahun', amount: 'Insidental' },
]

const navLinks = [
    { href: '#tentang', label: 'Tentang' },
    { href: '#program', label: 'Program' },
    { href: '#keunggulan', label: 'Keunggulan' },
    { href: '#biaya', label: 'Biaya' },
    { href: '#galeri', label: 'Galeri' },
    { href: '#testimoni', label: 'Testimoni' },
]

const mobileMenuOpen = ref(false)
const currentYear = new Date().getFullYear()

/* ------------------------------------------------------------------ *
 * Form pendaftaran — SALINAN PERSIS kontrak useForm + transform
 * ------------------------------------------------------------------ */
const extractNumericValue = (value) => {
    const digits = String(value ?? '').replace(/[^\d]/g, '')

    return digits === '' ? '' : Number.parseInt(digits, 10)
}
const defaultTransferAmount = extractNumericValue(admission.value.transfer?.nominal)

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
    foto_kk: null,
    foto_akta: null,
})

const submitAdmission = () => {
    form.transform((data) => ({
        ...data,
        nama_wali: data.nama_ibu || data.nama_ayah,
        hubungan_wali: 'Ibu',
        no_hp_wali: data.no_hp_ibu || data.no_hp_ayah,
        kelas_tujuan: admission.value.classOptions?.[0] ?? 'Kelompok Bermain',
        program_diminati: admission.value.programOptions?.[0] ?? 'Preschool',
        nama_pengirim_transfer: data.nama_ibu || data.nama_ayah,
        bank_pengirim: admission.value.transfer?.bankName || '',
        nominal_transfer: defaultTransferAmount || '',
        tanggal_transfer: new Date().toISOString().slice(0, 10),
    })).post(admission.value.endpoint, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('bukti_transfer'),
    })
}
</script>

<template>
    <div>
        <Head>
            <title>{{ seoTitle }}</title>
            <meta head-key="description" name="description" :content="seoDescription">
            <meta head-key="keywords" name="keywords" :content="seoKeywords">
            <meta head-key="robots" name="robots" :content="seo.robots || 'index,follow,max-image-preview:large'">
            <meta head-key="geo-region" name="geo.region" content="ID-JB">
            <meta head-key="geo-placename" name="geo.placename" content="Margaasih, Kabupaten Bandung">
            <meta head-key="theme-color" name="theme-color" content="#F5A623">
            <meta head-key="author" name="author" :content="brandName">
            <link head-key="canonical" rel="canonical" :href="seoUrl">
            <link head-key="favicon" rel="icon" type="image/png" :href="logoSrc">
            <link head-key="apple-touch-icon" rel="apple-touch-icon" :href="logoSrc">
            <meta head-key="og:title" property="og:title" :content="seoTitle">
            <meta head-key="og:description" property="og:description" :content="seoDescription">
            <meta head-key="og:type" property="og:type" content="website">
            <meta head-key="og:url" property="og:url" :content="seoUrl">
            <meta head-key="og:site_name" property="og:site_name" :content="brandName">
            <meta head-key="og:locale" property="og:locale" content="id_ID">
            <meta head-key="og:image" property="og:image" :content="seoImage">
            <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">
            <meta head-key="twitter:title" name="twitter:title" :content="seoTitle">
            <meta head-key="twitter:description" name="twitter:description" :content="seoDescription">
            <meta head-key="twitter:image" name="twitter:image" :content="seoImage">
        </Head>

        <div class="min-h-screen scroll-smooth bg-[#fffdf8] font-sans text-slate-800 antialiased">
            <!-- ============================== NAV ============================== -->
            <header class="sticky top-0 z-50 border-b border-[#ffe8c2]/80 bg-white/85 backdrop-blur-md">
                <nav class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8" aria-label="Navigasi utama">
                    <a href="#hero" class="flex items-center gap-3 rounded-2xl focus:outline-none focus-visible:ring-4 focus-visible:ring-[#29ABE2]/30">
                        <img
                            :src="logoSrc"
                            :alt="`Logo ${brandName}`"
                            class="h-12 w-12 shrink-0 object-contain"
                        >
                        <span class="flex flex-col leading-tight">
                            <span class="text-[0.65rem] font-bold uppercase tracking-[0.25em] text-[#29ABE2]">Welcome to</span>
                            <span class="text-lg font-extrabold text-[#F5A623]">{{ brandName }}</span>
                        </span>
                    </a>

                    <ul class="hidden items-center gap-1 lg:flex">
                        <li v-for="link in navLinks" :key="link.href">
                            <a
                                :href="link.href"
                                class="rounded-full px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#fff1d6] hover:text-[#df7a17] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#F5A623]/30"
                            >{{ link.label }}</a>
                        </li>
                    </ul>

                    <div class="flex items-center gap-2">
                        <a
                            href="#daftar"
                            class="hidden rounded-full bg-gradient-to-r from-[#F5A623] to-[#EC008C] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#EC008C]/20 transition hover:-translate-y-0.5 hover:shadow-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-[#EC008C]/30 sm:inline-flex"
                        >Daftar Sekarang</a>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-600 transition hover:bg-[#fff1d6] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#F5A623]/30 lg:hidden"
                            :aria-expanded="mobileMenuOpen"
                            aria-label="Buka menu navigasi"
                            @click="mobileMenuOpen = !mobileMenuOpen"
                        >
                            <svg v-if="!mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </nav>

                <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="-translate-y-2 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="-translate-y-2 opacity-0"
                >
                    <div v-if="mobileMenuOpen" class="border-t border-[#ffe8c2] bg-white px-4 py-3 lg:hidden">
                        <ul class="flex flex-col gap-1">
                            <li v-for="link in navLinks" :key="link.href">
                                <a
                                    :href="link.href"
                                    class="block rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-[#fff1d6] hover:text-[#df7a17]"
                                    @click="mobileMenuOpen = false"
                                >{{ link.label }}</a>
                            </li>
                            <li>
                                <a
                                    href="#daftar"
                                    class="mt-1 block rounded-xl bg-gradient-to-r from-[#F5A623] to-[#EC008C] px-4 py-2.5 text-center text-sm font-bold text-white"
                                    @click="mobileMenuOpen = false"
                                >Daftar Sekarang</a>
                            </li>
                        </ul>
                    </div>
                </transition>
            </header>

            <main>
                <!-- ============================== HERO ============================== -->
                <section id="hero" class="relative overflow-hidden">
                    <!-- Blob & aksen latar -->
                    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                        <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-[#29ABE2]/15 blur-3xl"></div>
                        <div class="absolute right-0 top-10 h-64 w-64 rounded-full bg-[#EC008C]/10 blur-3xl"></div>
                        <div class="absolute -bottom-20 left-1/3 h-72 w-72 rounded-full bg-[#F5A623]/15 blur-3xl"></div>
                        <svg class="absolute right-10 top-24 h-8 w-8 animate-pulse text-[#F7B500]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.6 6.4L21 9l-5 4.3L17.5 21 12 17.3 6.5 21 8 13.3 3 9l6.4-.6L12 2z"/></svg>
                        <svg class="absolute left-12 top-40 h-6 w-6 text-[#29ABE2]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.6 6.4L21 9l-5 4.3L17.5 21 12 17.3 6.5 21 8 13.3 3 9l6.4-.6L12 2z"/></svg>
                    </div>

                    <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-24">
                        <div class="text-center lg:text-left">
                            <span class="inline-flex items-center gap-2 rounded-full bg-[#fff1d6] px-4 py-1.5 text-sm font-bold text-[#df7a17]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.6 6.4L21 9l-5 4.3L17.5 21 12 17.3 6.5 21 8 13.3 3 9l6.4-.6L12 2z"/></svg>
                                Pendaftaran {{ academicYear }} telah dibuka
                            </span>

                            <h1 class="mt-5 text-4xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                                Tempat Pertama
                                <span class="relative whitespace-nowrap text-[#EC008C]">
                                    Si Kecil
                                    <svg class="absolute -bottom-2 left-0 h-3 w-full text-[#F5A623]" viewBox="0 0 200 12" preserveAspectRatio="none" fill="none" aria-hidden="true"><path d="M2 9c40-6 120-6 196-3" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
                                </span>
                                Belajar dengan Ceria
                            </h1>

                            <p class="mt-6 text-lg leading-relaxed text-slate-600 sm:text-xl">
                                Ruang tumbuh untuk bermain dan belajar dengan pendekatan montessori yang berlandaskan nilai tauhid dan sunnah Nabi Muhammad SAW.
                            </p>

                            <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row lg:items-start lg:justify-start">
                                <a
                                    href="#daftar"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[#F5A623] to-[#EC008C] px-7 py-3.5 text-base font-bold text-white shadow-xl shadow-[#EC008C]/25 transition hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus-visible:ring-4 focus-visible:ring-[#EC008C]/30 sm:w-auto"
                                >
                                    Daftar Sekarang
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M6 12h12"/></svg>
                                </a>
                                <a
                                    href="#program"
                                    class="inline-flex w-full items-center justify-center rounded-full border-2 border-[#29ABE2] bg-white px-7 py-3.5 text-base font-bold text-[#29ABE2] transition hover:bg-[#29ABE2] hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-[#29ABE2]/30 sm:w-auto"
                                >Lihat Program</a>
                            </div>

                            <ul class="mt-8 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 lg:justify-start">
                                <li v-for="badge in trustBadges" :key="badge" class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                                    <svg class="h-5 w-5 flex-shrink-0 text-[#1f9d8f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    {{ badge }}
                                </li>
                            </ul>
                        </div>

                        <!-- Ilustrasi hero -->
                        <div class="relative mx-auto w-full max-w-md lg:max-w-none">
                            <div class="absolute inset-0 -z-10 rotate-6 rounded-[3rem] bg-gradient-to-br from-[#29ABE2]/20 to-[#EC008C]/20" aria-hidden="true"></div>
                            <div class="relative overflow-hidden rounded-[3rem] border-4 border-white bg-gradient-to-br from-[#e8f6ff] via-white to-[#fff1d6] p-3 shadow-2xl shadow-[#29ABE2]/15">
                                <img
                                    src="/albakids/gal/foto-13.jpg"
                                    :alt="`Keceriaan anak-anak di ${brandName}`"
                                    loading="eager"
                                    class="aspect-[4/5] w-full rounded-[2.2rem] object-cover"
                                >

                                <!-- Kartu mengambang -->
                                <div class="absolute -left-3 top-10 hidden rotate-[-8deg] rounded-2xl bg-white px-3 py-2 shadow-lg sm:block">
                                    <p class="flex items-center gap-1.5 text-xs font-bold text-[#29ABE2]"><span class="text-base">🎨</span> Seni &amp; Kreasi</p>
                                </div>
                                <div class="absolute -right-3 bottom-12 hidden rotate-[6deg] rounded-2xl bg-white px-3 py-2 shadow-lg sm:block">
                                    <p class="flex items-center gap-1.5 text-xs font-bold text-[#EC008C]"><span class="text-base">📚</span> Cerita Seru</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gelombang pemisah -->
                    <div class="-mb-1" aria-hidden="true">
                        <svg viewBox="0 0 1440 80" class="w-full" preserveAspectRatio="none" fill="#ffffff"><path d="M0 40c240 40 480 40 720 16S1200 8 1440 32V80H0z"/></svg>
                    </div>
                </section>

                <!-- ============================== TENTANG ============================== -->
                <section id="tentang" class="bg-white py-16 lg:py-24">
                    <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                        <div class="relative order-2 lg:order-1">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-4">
                                    <img src="/albakids/gal/foto-10.jpg" alt="Anak-anak ceria Albakids" loading="lazy" class="h-40 w-full rounded-3xl object-cover shadow-sm">
                                    <img src="/albakids/gal/foto-9.jpg" alt="Kegiatan Albakids" loading="lazy" class="h-28 w-full rounded-3xl object-cover shadow-sm">
                                </div>
                                <div class="mt-8 space-y-4">
                                    <img src="/albakids/gal/foto-1.jpg" alt="Kebersamaan Albakids" loading="lazy" class="h-28 w-full rounded-3xl object-cover shadow-sm">
                                    <img src="/albakids/gal/foto-5.jpg" alt="Outing class Albakids" loading="lazy" class="h-40 w-full rounded-3xl object-cover shadow-sm">
                                </div>
                            </div>
                        </div>
                        <div class="order-1 lg:order-2">
                            <span class="inline-flex rounded-full bg-[#ffe0ef] px-4 py-1.5 text-sm font-bold text-[#EC008C]">Tentang Kami</span>
                            <h2 class="mt-4 text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">
                                Tumbuh, Bermain, dan Berkembang Bersama Kami
                            </h2>
                            <p class="mt-5 text-lg leading-relaxed text-slate-600">
                                Kami percaya bahwa setiap anak adalah amanah berharga yang memiliki potensi luar biasa untuk
                                berkembang melalui cinta, stimulasi yang tepat, dan lingkungan yang positif. Oleh karena itu,
                                ALBAKIDS menggabungkan pembelajaran akademik dasar, pendidikan karakter, kemandirian, kreativitas,
                                serta pembiasaan adab dan ibadah sejak dini.
                            </p>
                            <div class="mt-8 grid grid-cols-3 gap-4 text-center">
                                <div class="rounded-2xl bg-[#fffaf0] p-4">
                                    <p class="text-3xl font-extrabold text-[#F5A623]">Grow</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-500">Bertumbuh</p>
                                </div>
                                <div class="rounded-2xl bg-[#f0f9ff] p-4">
                                    <p class="text-3xl font-extrabold text-[#29ABE2]">Fun</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-500">menyenangkan</p>
                                </div>
                                <div class="rounded-2xl bg-[#fdf0f7] p-4">
                                    <p class="text-3xl font-extrabold text-[#EC008C]">Faith</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-500">dengan iman</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ============================== PROGRAM ============================== -->
                <section id="program" class="relative overflow-hidden bg-[#fffdf8] py-16 lg:py-24">
                    <div class="pointer-events-none absolute -right-16 top-16 -z-10 h-64 w-64 rounded-full bg-[#F5A623]/10 blur-3xl" aria-hidden="true"></div>
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <span class="inline-flex rounded-full bg-[#fff1d6] px-4 py-1.5 text-sm font-bold text-[#df7a17]">Program Kami</span>
                            <h2 class="mt-4 text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">Yang Anak Bunda Pelajari</h2>
                            <p class="mt-4 text-lg text-slate-600">Pembelajaran yang memadukan akademik dasar, pendidikan karakter, serta pembiasaan adab &amp; ibadah sejak dini.</p>
                        </div>

                        <div class="mx-auto mt-12 grid max-w-4xl gap-4 sm:grid-cols-2">
                            <div
                                v-for="(item, index) in curriculum"
                                :key="item"
                                class="flex items-start gap-4 rounded-2xl border border-[#ffe8c2] bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <span
                                    class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-white shadow-sm"
                                    :class="['bg-[#29ABE2]', 'bg-[#EC008C]', 'bg-[#F5A623]', 'bg-[#1f9d8f]'][index % 4]"
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </span>
                                <p class="text-base font-semibold leading-relaxed text-slate-700">{{ item }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ============================== KEUNGGULAN ============================== -->
                <section id="keunggulan" class="bg-gradient-to-b from-white to-[#f0f9ff] py-16 lg:py-24">
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <span class="inline-flex rounded-full bg-[#e6faf6] px-4 py-1.5 text-sm font-bold text-[#1f9d8f]">Mengapa Albakids</span>
                            <h2 class="mt-4 text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">Alasan Orang Tua Mempercayakan Kami</h2>
                            <p class="mt-4 text-lg text-slate-600">Kami hadir untuk menemani langkah pertama si kecil dengan sepenuh hati.</p>
                        </div>

                        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="(item, index) in advantages"
                                :key="item.title"
                                class="rounded-3xl border border-white bg-white/80 p-6 shadow-sm backdrop-blur transition hover:-translate-y-1 hover:shadow-lg"
                            >
                                <div
                                    class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl"
                                    :class="['bg-[#fff1d6] text-[#F5A623]', 'bg-[#ffe0ef] text-[#EC008C]', 'bg-[#e8f6ff] text-[#29ABE2]'][index % 3]"
                                >
                                    <svg v-if="item.icon === 'heart'" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-4.6-9.5-9A5 5 0 0112 5a5 5 0 019.5 7c-2.5 4.4-9.5 9-9.5 9z"/></svg>
                                    <svg v-else-if="item.icon === 'sparkles'" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2 6 6 2-6 2-2 6-2-6-6-2 6-2 2-6zM19 13l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z"/></svg>
                                    <svg v-else-if="item.icon === 'shield'" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                                    <svg v-else-if="item.icon === 'palette'" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 100 18 2.5 2.5 0 002-4c-.5-.7-.3-1.7.5-2H17a4 4 0 004-4c0-4.4-4-8-9-8z"/><circle cx="7.5" cy="11" r="1.2" fill="currentColor"/><circle cx="10" cy="7.5" r="1.2" fill="currentColor"/><circle cx="14.5" cy="7.5" r="1.2" fill="currentColor"/></svg>
                                    <svg v-else-if="item.icon === 'users'" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 20a6 6 0 0112 0M16 6a3 3 0 010 6M21 20a6 6 0 00-4-5.6"/></svg>
                                    <svg v-else class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a8 8 0 01-11.6 7.1L4 20l1-5.2A8 8 0 1121 12z"/></svg>
                                </div>
                                <p class="text-lg font-bold leading-relaxed text-slate-800">{{ item.text }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ============================== GALERI ============================== -->
                <section id="galeri" class="bg-white py-16 lg:py-24">
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <span class="inline-flex rounded-full bg-[#ffe0ef] px-4 py-1.5 text-sm font-bold text-[#EC008C]">Galeri Keceriaan</span>
                            <h2 class="mt-4 text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">Momen Seru di Alba Kids</h2>
                            <p class="mt-4 text-lg text-slate-600">Sekilas suasana belajar dan bermain yang penuh tawa setiap harinya.</p>
                        </div>

                        <div class="mt-12 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:gap-5">
                            <figure
                                v-for="page in albaBrochurePages"
                                :key="page.src"
                                data-gallery-item
                                class="group relative aspect-square overflow-hidden rounded-3xl bg-[#f0f9ff] shadow-sm ring-1 ring-[#ffe8c2]"
                            >
                                <img
                                    :src="page.src"
                                    :alt="page.alt"
                                    loading="lazy"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-110"
                                    @error="handleImageError"
                                >
                                <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/50 to-transparent p-3 text-xs font-semibold text-white opacity-0 transition group-hover:opacity-100">
                                    {{ page.alt }}
                                </figcaption>
                            </figure>
                        </div>
                    </div>
                </section>

                <!-- ============================== TESTIMONI ============================== -->
                <section id="testimoni" class="relative overflow-hidden bg-[#f0f9ff] py-16 lg:py-24">
                    <div class="pointer-events-none absolute -left-16 bottom-10 -z-10 h-64 w-64 rounded-full bg-[#EC008C]/10 blur-3xl" aria-hidden="true"></div>
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <span class="inline-flex rounded-full bg-white px-4 py-1.5 text-sm font-bold text-[#29ABE2] shadow-sm">Kata Orang Tua</span>
                            <h2 class="mt-4 text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">Cerita Bahagia dari Keluarga</h2>
                        </div>

                        <div class="mt-12 grid gap-6 lg:grid-cols-3">
                            <figure
                                v-for="item in testimonials"
                                :key="item.name"
                                class="flex flex-col rounded-3xl border border-white bg-white p-7 shadow-sm"
                            >
                                <div class="flex gap-0.5 text-[#F7B500]" role="img" aria-label="Penilaian lima dari lima bintang">
                                    <svg v-for="n in 5" :key="n" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.6 6.4L21 9l-5 4.3L17.5 21 12 17.3 6.5 21 8 13.3 3 9l6.4-.6L12 2z"/></svg>
                                </div>
                                <blockquote class="mt-4 flex-1 text-lg leading-relaxed text-slate-700">"{{ item.quote }}"</blockquote>
                                <figcaption class="mt-6 flex items-center gap-3">
                                    <span
                                        class="flex h-12 w-12 items-center justify-center rounded-full text-lg font-extrabold text-white"
                                        :style="{ backgroundColor: item.color }"
                                        aria-hidden="true"
                                    >{{ item.initial }}</span>
                                    <span>
                                        <span class="block font-extrabold text-slate-900">{{ item.name }}</span>
                                        <span class="block text-sm text-slate-500">{{ item.role }}</span>
                                    </span>
                                </figcaption>
                            </figure>
                        </div>
                    </div>
                </section>

                <!-- ============================== BIAYA ============================== -->
                <section id="biaya" class="bg-white py-16 lg:py-24">
                    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <span class="inline-flex rounded-full bg-[#fff1d6] px-4 py-1.5 text-sm font-bold text-[#df7a17]">Biaya {{ academicYear }}</span>
                            <h2 class="mt-4 text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">Rincian Biaya Pendaftaran</h2>
                        </div>
                        <div class="mt-10 overflow-hidden rounded-3xl border border-[#ffd9e6] shadow-sm">
                            <div class="flex items-center justify-between bg-[#f48aa0] px-6 py-4 text-base font-extrabold uppercase tracking-wide text-white">
                                <span>Kategori</span><span>Biaya</span>
                            </div>
                            <div
                                v-for="(fee, i) in feeItems"
                                :key="fee.label"
                                class="flex items-center justify-between gap-4 px-6 py-3.5 text-sm font-semibold text-slate-700"
                                :class="i % 2 === 0 ? 'bg-[#fde6ec]' : 'bg-[#fff5f8]'"
                            >
                                <span>{{ fee.label }}</span><span class="whitespace-nowrap">{{ fee.amount }}</span>
                            </div>
                            <div class="flex items-center justify-between bg-[#f48aa0] px-6 py-4 text-lg font-extrabold text-white">
                                <span>Total</span><span>Rp 1.785.000</span>
                            </div>
                        </div>
                        <p class="mx-auto mt-5 max-w-xl text-center text-sm font-medium text-slate-500">NB: Khusus bagi putra-putri guru/staff Al-Basyariyah berlaku biaya tersendiri. Silakan menghubungi admin.</p>
                    </div>
                </section>

                <!-- ============================== DAFTAR (FORM) ============================== -->
                <section id="daftar" class="relative overflow-hidden bg-gradient-to-b from-white to-[#fffaf0] py-16 lg:py-24">
                    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                        <div class="absolute -right-20 top-20 h-72 w-72 rounded-full bg-[#F5A623]/10 blur-3xl"></div>
                        <div class="absolute -left-20 bottom-0 h-72 w-72 rounded-full bg-[#29ABE2]/10 blur-3xl"></div>
                    </div>

                    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <span class="inline-flex items-center gap-2 rounded-full bg-[#fff1d6] px-4 py-1.5 text-sm font-bold text-[#df7a17]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.6 6.4L21 9l-5 4.3L17.5 21 12 17.3 6.5 21 8 13.3 3 9l6.4-.6L12 2z"/></svg>
                                Formulir Pendaftaran {{ academicYear }}
                            </span>
                            <h2 class="mt-4 text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">Yuk, Daftarkan Si Kecil Sekarang</h2>
                            <p class="mt-4 text-lg text-slate-600">Lengkapi data berikut, unggah bukti transfer, lalu tim kami akan segera menghubungi Anda melalui WhatsApp.</p>
                        </div>

                        <div class="mt-10 grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
                            <!-- Panel info & pembayaran -->
                            <aside class="space-y-5">
                                <div class="rounded-3xl border-l-4 border-[#F5A623] bg-white p-6 shadow-sm">
                                    <h3 class="flex items-center gap-2 text-lg font-extrabold text-slate-900">
                                        <svg class="h-5 w-5 text-[#F5A623]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="3"/><path stroke-linecap="round" d="M2 10h20"/></svg>
                                        Info Pembayaran
                                    </h3>
                                    <p v-if="admission.transfer?.nominal" class="mt-3 text-2xl font-extrabold text-[#EC008C]">{{ admission.transfer.nominal }}</p>
                                    <dl class="mt-4 space-y-2 rounded-2xl bg-[#fffaf0] p-4 text-sm">
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-slate-500">Bank</dt>
                                            <dd class="font-bold text-slate-800">{{ admission.transfer?.bankName || '-' }}</dd>
                                        </div>
                                        <div v-if="admission.transfer?.accountNumber" class="flex justify-between gap-3">
                                            <dt class="text-slate-500">No. Rekening</dt>
                                            <dd class="font-bold text-slate-800">{{ admission.transfer.accountNumber }}</dd>
                                        </div>
                                        <div v-if="admission.transfer?.accountName" class="flex justify-between gap-3">
                                            <dt class="text-slate-500">Atas Nama</dt>
                                            <dd class="font-bold text-slate-800">{{ admission.transfer.accountName }}</dd>
                                        </div>
                                    </dl>
                                    <p class="mt-3 text-sm italic leading-relaxed text-slate-500">
                                        Setelah transfer, unggah bukti pembayaran pada formulir agar panitia dapat langsung memverifikasi.
                                    </p>
                                </div>

                                <div class="rounded-3xl bg-[#e8f6ff] p-6">
                                    <h3 class="text-lg font-extrabold text-[#29ABE2]">Butuh Bantuan?</h3>
                                    <ul class="mt-4 space-y-3 text-sm text-slate-700">
                                        <li class="flex items-start gap-2">
                                            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#1f9d8f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3l2 5-2 1a11 11 0 005 5l1-2 5 2v3a2 2 0 01-2 2A16 16 0 013 5z"/></svg>
                                            <span>Admin: <span class="font-bold text-slate-900">{{ websiteContact.phone || 'Hubungi kami' }}</span></span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#1f9d8f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-5-7-11a7 7 0 0114 0c0 6-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                            <span>{{ websiteContact.address || 'Bandung, Jawa Barat' }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </aside>

                            <!-- Form -->
                            <div class="rounded-3xl border border-[#ffe8c2] bg-white p-6 shadow-lg sm:p-8">
                                <div
                                    v-if="flash.success"
                                    class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-800"
                                    role="status"
                                >
                                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span class="font-semibold">{{ flash.success }}</span>
                                </div>

                                <div
                                    v-if="Object.keys(form.errors).length"
                                    class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700"
                                    role="alert"
                                >
                                    Mohon periksa kembali kolom yang masih perlu dilengkapi.
                                </div>

                                <form class="space-y-8" novalidate @submit.prevent="submitAdmission">
                                    <!-- Data anak -->
                                    <fieldset class="space-y-4">
                                        <legend class="flex items-center gap-2 text-base font-extrabold text-slate-900">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#29ABE2] text-sm text-white">1</span>
                                            Data Anak
                                        </legend>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700 sm:col-span-2">
                                                <span>Nama Lengkap Anak <span class="text-rose-500">*</span></span>
                                                <input v-model="form.nama_anak" type="text" required class="alba-input">
                                                <span v-if="form.errors.nama_anak" class="alba-error">{{ form.errors.nama_anak }}</span>
                                            </label>

                                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                                <span>Jenis Kelamin <span class="text-rose-500">*</span></span>
                                                <select v-model="form.jenis_kelamin" required class="alba-input">
                                                    <option value="">Pilih jenis kelamin</option>
                                                    <option value="L">Laki-laki</option>
                                                    <option value="P">Perempuan</option>
                                                </select>
                                                <span v-if="form.errors.jenis_kelamin" class="alba-error">{{ form.errors.jenis_kelamin }}</span>
                                            </label>

                                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                                <span>Anak ke- <span class="text-rose-500">*</span></span>
                                                <input v-model="form.child_order" type="text" required placeholder="Contoh: 2" class="alba-input">
                                                <span v-if="form.errors.child_order" class="alba-error">{{ form.errors.child_order }}</span>
                                            </label>

                                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                                <span>Tempat Lahir <span class="text-rose-500">*</span></span>
                                                <input v-model="form.tempat_lahir" type="text" required placeholder="Contoh: Bandung" class="alba-input">
                                                <span v-if="form.errors.tempat_lahir" class="alba-error">{{ form.errors.tempat_lahir }}</span>
                                            </label>

                                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                                <span>Tanggal Lahir <span class="text-rose-500">*</span></span>
                                                <input v-model="form.tanggal_lahir" type="date" required class="alba-input">
                                                <span v-if="form.errors.tanggal_lahir" class="alba-error">{{ form.errors.tanggal_lahir }}</span>
                                            </label>
                                        </div>
                                    </fieldset>

                                    <!-- Data orang tua -->
                                    <fieldset class="space-y-4">
                                        <legend class="flex items-center gap-2 text-base font-extrabold text-slate-900">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#EC008C] text-sm text-white">2</span>
                                            Data Orang Tua
                                        </legend>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                                <span>Nama Ayah <span class="text-rose-500">*</span></span>
                                                <input v-model="form.nama_ayah" type="text" required class="alba-input">
                                                <span v-if="form.errors.nama_ayah" class="alba-error">{{ form.errors.nama_ayah }}</span>
                                            </label>

                                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                                <span>Nama Ibu <span class="text-rose-500">*</span></span>
                                                <input v-model="form.nama_ibu" type="text" required class="alba-input">
                                                <span v-if="form.errors.nama_ibu" class="alba-error">{{ form.errors.nama_ibu }}</span>
                                            </label>

                                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                                <span>Pekerjaan Ayah <span class="text-rose-500">*</span></span>
                                                <input v-model="form.pekerjaan_ayah" type="text" required class="alba-input">
                                                <span v-if="form.errors.pekerjaan_ayah" class="alba-error">{{ form.errors.pekerjaan_ayah }}</span>
                                            </label>

                                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                                <span>Pekerjaan Ibu <span class="text-rose-500">*</span></span>
                                                <input v-model="form.pekerjaan_ibu" type="text" required class="alba-input">
                                                <span v-if="form.errors.pekerjaan_ibu" class="alba-error">{{ form.errors.pekerjaan_ibu }}</span>
                                            </label>

                                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                                <span>No. WhatsApp Ayah <span class="text-rose-500">*</span></span>
                                                <input v-model="form.no_hp_ayah" type="tel" required placeholder="628xxxxxxxxxx" class="alba-input">
                                                <span v-if="form.errors.no_hp_ayah" class="alba-error">{{ form.errors.no_hp_ayah }}</span>
                                            </label>

                                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                                <span>No. WhatsApp Ibu <span class="text-rose-500">*</span></span>
                                                <input v-model="form.no_hp_ibu" type="tel" required placeholder="628xxxxxxxxxx" class="alba-input">
                                                <span v-if="form.errors.no_hp_ibu" class="alba-error">{{ form.errors.no_hp_ibu }}</span>
                                            </label>
                                        </div>
                                    </fieldset>

                                    <!-- Alamat & bukti -->
                                    <fieldset class="space-y-4">
                                        <legend class="flex items-center gap-2 text-base font-extrabold text-slate-900">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#F5A623] text-sm text-white">3</span>
                                            Alamat &amp; Dokumen
                                        </legend>
                                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                            <span>Alamat Lengkap <span class="text-rose-500">*</span></span>
                                            <textarea v-model="form.alamat" rows="3" required placeholder="Jalan, kelurahan, kecamatan, kota, provinsi" class="alba-input resize-none"></textarea>
                                            <span v-if="form.errors.alamat" class="alba-error">{{ form.errors.alamat }}</span>
                                        </label>

                                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                            <span>Upload Bukti Transfer <span class="text-rose-500">*</span></span>
                                            <input
                                                type="file"
                                                accept=".jpg,.jpeg,.png,.pdf,.webp"
                                                required
                                                class="block w-full cursor-pointer rounded-2xl border border-dashed border-[#29ABE2]/50 bg-[#f0f9ff] px-4 py-3 text-sm text-slate-600 transition file:mr-4 file:cursor-pointer file:rounded-full file:border-0 file:bg-[#29ABE2] file:px-4 file:py-2 file:font-bold file:text-white hover:bg-[#e1f3ff] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#29ABE2]/30"
                                                @input="form.bukti_transfer = $event.target.files[0]"
                                            >
                                            <span class="text-xs font-normal text-slate-400">Sudah transfer Rp 1.785.000 ke Bank Mandiri a.n. Inna Siti Nurhasanah (1300005641850). Format: JPG, JPEG, PNG, PDF, atau WEBP.</span>
                                            <span v-if="form.errors.bukti_transfer" class="alba-error">{{ form.errors.bukti_transfer }}</span>
                                        </label>

                                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                            <span>Foto Kartu Keluarga (KK) <span class="text-rose-500">*</span></span>
                                            <input
                                                type="file"
                                                accept=".jpg,.jpeg,.png,.pdf,.webp"
                                                required
                                                class="block w-full cursor-pointer rounded-2xl border border-dashed border-[#29ABE2]/50 bg-[#f0f9ff] px-4 py-3 text-sm text-slate-600 transition file:mr-4 file:cursor-pointer file:rounded-full file:border-0 file:bg-[#29ABE2] file:px-4 file:py-2 file:font-bold file:text-white hover:bg-[#e1f3ff] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#29ABE2]/30"
                                                @input="form.foto_kk = $event.target.files[0]"
                                            >
                                            <span class="text-xs font-normal text-slate-400">Format: JPG, JPEG, PNG, PDF, atau WEBP.</span>
                                            <span v-if="form.errors.foto_kk" class="alba-error">{{ form.errors.foto_kk }}</span>
                                        </label>

                                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                            <span>Foto Akta Kelahiran <span class="text-rose-500">*</span></span>
                                            <input
                                                type="file"
                                                accept=".jpg,.jpeg,.png,.pdf,.webp"
                                                required
                                                class="block w-full cursor-pointer rounded-2xl border border-dashed border-[#29ABE2]/50 bg-[#f0f9ff] px-4 py-3 text-sm text-slate-600 transition file:mr-4 file:cursor-pointer file:rounded-full file:border-0 file:bg-[#29ABE2] file:px-4 file:py-2 file:font-bold file:text-white hover:bg-[#e1f3ff] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#29ABE2]/30"
                                                @input="form.foto_akta = $event.target.files[0]"
                                            >
                                            <span class="text-xs font-normal text-slate-400">Format: JPG, JPEG, PNG, PDF, atau WEBP.</span>
                                            <span v-if="form.errors.foto_akta" class="alba-error">{{ form.errors.foto_akta }}</span>
                                        </label>
                                    </fieldset>

                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[#F5A623] to-[#EC008C] py-4 text-base font-bold text-white shadow-xl shadow-[#EC008C]/25 transition hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus-visible:ring-4 focus-visible:ring-[#EC008C]/30 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
                                    >
                                        <svg v-if="form.processing" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path d="M12 2a10 10 0 0110 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
                                        {{ form.processing ? 'Mengirim pendaftaran...' : 'Kirim Pendaftaran' }}
                                    </button>
                                    <p class="text-center text-xs text-slate-400">Dengan mengirim, Anda menyetujui tim kami menghubungi Anda untuk proses verifikasi.</p>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <!-- ============================== FOOTER ============================== -->
            <footer class="bg-[#1f2937] text-slate-300">
                <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
                    <div class="grid gap-10 md:grid-cols-3">
                        <div>
                            <div class="flex items-center gap-3">
                                <img :src="logoSrc" :alt="`Logo ${brandName}`" class="h-12 w-12 rounded-full bg-white object-contain p-1 ring-2 ring-[#F5A623]/60">
                                <span class="text-xl font-extrabold text-white">{{ brandName }}</span>
                            </div>
                            <p class="mt-4 max-w-xs text-sm leading-relaxed text-slate-400">
                                Ruang tumbuh yang ceria dan hangat untuk langkah awal si kecil belajar di Bandung.
                            </p>
                        </div>

                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-white">Kontak</h3>
                            <ul class="mt-4 space-y-3 text-sm text-slate-400">
                                <li class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#F5A623]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-5-7-11a7 7 0 0114 0c0 6-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                    <span>{{ websiteContact.address || 'Bandung, Jawa Barat' }}</span>
                                </li>
                                <li v-if="websiteContact.phone" class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#29ABE2]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3l2 5-2 1a11 11 0 005 5l1-2 5 2v3a2 2 0 01-2 2A16 16 0 013 5z"/></svg>
                                    <a :href="`tel:${websiteContact.phone}`" class="transition hover:text-white">{{ websiteContact.phone }}</a>
                                </li>
                                <li v-if="websiteContact.email" class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#EC008C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9 6 9-6"/></svg>
                                    <a :href="`mailto:${websiteContact.email}`" class="transition hover:text-white">{{ websiteContact.email }}</a>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-white">Ikuti Kami</h3>
                            <p class="mt-4 text-sm text-slate-400">Lihat keseruan harian kami di Instagram.</p>
                            <a
                                href="https://www.instagram.com/albakids_preschool.bdg/"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-[#F5A623] via-[#EC008C] to-[#29ABE2] px-5 py-2.5 text-sm font-bold text-white transition hover:-translate-y-0.5 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#EC008C]/30"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2c3.2 0 3.6 0 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.43.36 1.06.41 2.23.06 1.25.07 1.65.07 4.85s0 3.6-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.43.16-1.06.36-2.23.41-1.25.06-1.65.07-4.85.07s-3.6 0-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 01-1.38-.9 3.7 3.7 0 01-.9-1.38c-.16-.43-.36-1.06-.41-2.23C2.2 15.6 2.2 15.2 2.2 12s0-3.6.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.43-.16 1.06-.36 2.23-.41C8.4 2.2 8.8 2.2 12 2.2zm0 1.8c-3.14 0-3.51.01-4.75.07-.9.04-1.39.19-1.71.32-.43.17-.74.37-1.06.69-.32.32-.52.63-.69 1.06-.13.32-.28.81-.32 1.71-.06 1.24-.07 1.61-.07 4.75s.01 3.51.07 4.75c.04.9.19 1.39.32 1.71.17.43.37.74.69 1.06.32.32.63.52 1.06.69.32.13.81.28 1.71.32 1.24.06 1.61.07 4.75.07s3.51-.01 4.75-.07c.9-.04 1.39-.19 1.71-.32.43-.17.74-.37 1.06-.69.32-.32.52-.63.69-1.06.13-.32.28-.81.32-1.71.06-1.24.07-1.61.07-4.75s-.01-3.51-.07-4.75c-.04-.9-.19-1.39-.32-1.71a2.85 2.85 0 00-.69-1.06 2.85 2.85 0 00-1.06-.69c-.32-.13-.81-.28-1.71-.32C15.51 4.01 15.14 4 12 4zm0 3.07a4.93 4.93 0 110 9.86 4.93 4.93 0 010-9.86zm0 1.8a3.13 3.13 0 100 6.26 3.13 3.13 0 000-6.26zm5.15-.7a1.15 1.15 0 110 2.3 1.15 1.15 0 010-2.3z"/></svg>
                                @albakids_preschool.bdg
                            </a>
                        </div>
                    </div>

                    <div class="mt-12 flex flex-col items-center justify-between gap-3 border-t border-white/10 pt-6 text-sm text-slate-500 sm:flex-row">
                        <p>&copy; {{ currentYear }} {{ brandName }}. Seluruh hak cipta dilindungi.</p>
                        <a href="#daftar" class="font-semibold text-[#F5A623] transition hover:text-[#ffc75f]">Daftar Sekarang</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.alba-input {
    width: 100%;
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
    background-color: #fbfdff;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    font-weight: 400;
    color: #334155;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}

.alba-input:focus {
    outline: none;
    border-color: #29ABE2;
    background-color: #ffffff;
    box-shadow: 0 0 0 4px rgba(41, 171, 226, 0.15);
}

.alba-input::placeholder {
    color: #94a3b8;
}

.alba-error {
    font-size: 0.75rem;
    font-weight: 600;
    color: #e11d48;
}

.alba-gallery-empty {
    display: flex;
    align-items: center;
    justify-content: center;
}

.alba-gallery-empty::after {
    content: '🎈';
    font-size: 2.5rem;
    opacity: 0.5;
}
</style>
