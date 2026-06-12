<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatRupiahFull } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
const props = defineProps({ can: Object })

const loading = ref(true)
const data = ref(null)
const error = ref(null)

const shortcuts = [
    { label: 'Tagihan', icon: 'receipt', href: '/keuangan/penerimaan/tagihan', color: 'emerald' },
    { label: 'Verifikasi', icon: 'check', href: '/keuangan/penerimaan/verifikasi', color: 'blue' },
    { label: 'Loket Kas', icon: 'banknotes', href: '/keuangan/penerimaan/loket', color: 'amber' },
    { label: 'Tabungan', icon: 'piggy', href: '/keuangan/kas-harian/tabungan', color: 'teal' },
    { label: 'Approval', icon: 'stamp', href: '/keuangan/pengeluaran/approval', color: 'rose' },
    { label: 'Payroll', icon: 'users', href: '/keuangan/pengeluaran/payroll', color: 'purple' },
    { label: 'Jurnal', icon: 'book', href: '/keuangan/ledger/jurnal', color: 'indigo' },
    { label: 'Laporan', icon: 'chart', href: '/keuangan/laporan', color: 'orange' },
]

onMounted(async () => {
    try {
        data.value = await api.get('/beranda')
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <!-- Posisi Kas -->
    <section class="mb-5">
        <h2 class="mb-3 font-['Marcellus',serif] text-base text-[#0A5C36]">Posisi Kas</h2>

        <!-- Skeleton -->
        <div v-if="loading" class="space-y-3">
            <div class="h-24 animate-pulse rounded-2xl bg-[#e8e1d0]/60"></div>
            <div class="grid grid-cols-2 gap-3">
                <div class="h-16 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
                <div class="h-16 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
            </div>
        </div>

        <div v-else-if="error" class="rounded-2xl bg-red-50 px-4 py-5 text-sm text-red-700">
            Gagal memuat data: {{ error }}
        </div>

        <div v-else>
            <!-- Total kas card -->
            <div class="mb-3 rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-5 text-white shadow-lg"
                 style="border: 1px solid rgba(201,162,74,0.3);">
                <div class="mb-1 text-[11px] uppercase tracking-wider text-[#e7d3a1]">Total Posisi Kas</div>
                <div class="font-['Marcellus',serif] text-2xl">
                    {{ formatRupiahFull(data?.totalKas ?? 0) }}
                </div>
                <div class="mt-2 text-xs opacity-75">
                    Periode: {{ data?.periodeAktif ?? '-' }}
                </div>
            </div>

            <!-- Kas & Bank breakdown -->
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-[#ece3cf] bg-white p-3.5">
                    <div class="mb-0.5 text-[10px] uppercase tracking-wider text-[#8a9690]">Kas Tunai</div>
                    <div class="font-semibold text-[#0A5C36]">{{ formatRupiahFull(data?.kasTunai ?? 0) }}</div>
                </div>
                <div class="rounded-xl border border-[#ece3cf] bg-white p-3.5">
                    <div class="mb-0.5 text-[10px] uppercase tracking-wider text-[#8a9690]">Bank</div>
                    <div class="font-semibold text-[#0A5C36]">{{ formatRupiahFull(data?.kasBank ?? 0) }}</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Approval Inbox -->
    <section v-if="props.can?.approve_pencairan" class="mb-5">
        <div class="flex items-center justify-between">
            <h2 class="font-['Marcellus',serif] text-base text-[#0A5C36]">Menunggu Persetujuan</h2>
            <a href="/keuangan/pengeluaran/approval"
               class="text-xs font-semibold text-[#C9A24A] hover:underline">
                Lihat Semua
            </a>
        </div>

        <div v-if="loading" class="mt-3 space-y-2">
            <div v-for="i in 3" :key="i" class="h-14 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
        </div>

        <div v-else-if="data?.pendingApproval?.length" class="mt-3 space-y-2">
            <div v-for="item in data.pendingApproval.slice(0, 5)" :key="item.id"
                 class="flex items-center gap-3 rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium">{{ item.keterangan }}</div>
                    <div class="text-xs text-[#8a9690]">{{ item.pemohon }} · {{ formatRupiah(item.nominal) }}</div>
                </div>
            </div>
        </div>

        <div v-else class="mt-3 rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-6 text-center text-sm text-[#8a9690]">
            Tidak ada pengajuan menunggu
        </div>
    </section>

    <!-- Quick Shortcuts -->
    <section class="mb-5">
        <h2 class="mb-3 font-['Marcellus',serif] text-base text-[#0A5C36]">Pintasan</h2>
        <div class="grid grid-cols-4 gap-2.5">
            <a v-for="s in shortcuts" :key="s.label" :href="s.href"
               class="flex flex-col items-center gap-1.5 rounded-xl border border-[#ece3cf] bg-white px-2 py-3 text-center transition hover:border-[#C9A24A]/50 hover:shadow-sm">
                <!-- Icons -->
                <div class="flex h-9 w-9 items-center justify-center rounded-xl"
                     :class="{
                         'bg-emerald-50 text-emerald-600': s.color === 'emerald',
                         'bg-blue-50 text-blue-600': s.color === 'blue',
                         'bg-amber-50 text-amber-600': s.color === 'amber',
                         'bg-teal-50 text-teal-600': s.color === 'teal',
                         'bg-rose-50 text-rose-600': s.color === 'rose',
                         'bg-purple-50 text-purple-600': s.color === 'purple',
                         'bg-indigo-50 text-indigo-600': s.color === 'indigo',
                         'bg-orange-50 text-orange-600': s.color === 'orange',
                     }">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <!-- receipt -->
                        <template v-if="s.icon === 'receipt'"><rect x="5" y="2" width="14" height="20" rx="1" /><path d="M9 6h6M9 10h6M9 14h3" /></template>
                        <!-- check -->
                        <template v-else-if="s.icon === 'check'"><path d="M5 13l4 4L19 7" /></template>
                        <!-- banknotes -->
                        <template v-else-if="s.icon === 'banknotes'"><rect x="2" y="6" width="20" height="12" rx="2" /><circle cx="12" cy="12" r="3" /></template>
                        <!-- piggy -->
                        <template v-else-if="s.icon === 'piggy'"><path d="M19 10c0-4-3.6-7-8-7S3 6 3 10c0 3 2 5.5 5 6.5V19a1 1 0 0 0 1 1h1M15 10c0 3-2 5.5-5 6.5" /><circle cx="8" cy="9" r="1" fill="currentColor" /></template>
                        <!-- stamp -->
                        <template v-else-if="s.icon === 'stamp'"><path d="M5 21h14M7 21v-3a5 5 0 0 1 10 0v3M12 12V6" /><circle cx="12" cy="4" r="2" /></template>
                        <!-- users -->
                        <template v-else-if="s.icon === 'users'"><circle cx="9" cy="7" r="3" /><path d="M3 19c0-3 3-5 6-5s6 2 6 5" /><circle cx="17" cy="7" r="2" /><path d="M21 19c0-2-2-4-4-4" /></template>
                        <!-- book -->
                        <template v-else-if="s.icon === 'book'"><path d="M4 4h4c2 0 4 1 4 3s-2 3-4 3H4zm0 0v16m0 0h4c2 0 4-1 4-3s-2-3-4-3H4M20 4h-4c-2 0-4 1-4 3m8-3v16m0 0h-4c-2 0-4-1-4-3" /></template>
                        <!-- chart -->
                        <template v-else><rect x="3" y="12" width="4" height="8" rx="1" /><rect x="10" y="8" width="4" height="12" rx="1" /><rect x="17" y="4" width="4" height="16" rx="1" /></template>
                    </svg>
                </div>
                <span class="text-[10px] font-semibold leading-tight text-[#3a4d42]">{{ s.label }}</span>
            </a>
        </div>
    </section>

    <!-- Ringkasan Harian -->
    <section v-if="!loading && data?.ringkasanHarian" class="mb-5">
        <h2 class="mb-3 font-['Marcellus',serif] text-base text-[#0A5C36]">Ringkasan Hari Ini</h2>
        <div class="space-y-2">
            <div class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                <span class="text-sm text-[#8a9690]">Penerimaan</span>
                <span class="font-semibold text-emerald-600">+{{ formatRupiahFull(data.ringkasanHarian.penerimaan ?? 0) }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                <span class="text-sm text-[#8a9690]">Pengeluaran</span>
                <span class="font-semibold text-rose-600">−{{ formatRupiahFull(data.ringkasanHarian.pengeluaran ?? 0) }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                <span class="text-sm text-[#8a9690]">Belum Diverifikasi</span>
                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700">
                    {{ data.ringkasanHarian.belumVerifikasi ?? 0 }}
                </span>
            </div>
        </div>
    </section>
</template>
