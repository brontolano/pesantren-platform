<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull, formatTanggal } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
const props = defineProps({ can: Object })

const loading = ref(true)
const data = ref(null)

const tutup = ref(null)
const tutupLoading = ref(false)
const tutupPesan = ref(null)

async function muatPratinjau() {
    if (!props.can?.close_period) return
    try { tutup.value = await api.get('/laporan/tutup-buku') } catch { /* */ }
}

async function kunciPeriode() {
    if (!tutup.value?.periode) return
    if (!confirm(`Tutup buku periode ${tutup.value.periode}? Semua akun pendapatan & beban akan di-nol-kan ke Aset Neto dan periode DIKUNCI.`)) return
    const ketik = prompt('Ketik TUTUP untuk konfirmasi akhir:')
    if (ketik !== 'TUTUP') return

    tutupLoading.value = true
    tutupPesan.value = null
    try {
        const res = await api.post('/laporan/tutup-buku', { konfirmasi: 'TUTUP' })
        tutupPesan.value = { ok: true, teks: res.message }
        await muatPratinjau()
    } catch (e) {
        tutupPesan.value = { ok: false, teks: e.message }
    } finally {
        tutupLoading.value = false
    }
}

onMounted(async () => {
    try { data.value = await api.get('/laporan/arus-kas') } catch { /* */ }
    loading.value = false
    muatPratinjau()
})
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Arus Kas & Tutup Buku</h1>

        <div v-if="loading" class="space-y-3">
            <div v-for="i in 5" :key="i" class="h-12 animate-pulse rounded-lg bg-[#e8e1d0]/60"></div>
        </div>

        <template v-else-if="data">
            <div v-for="section in data.sections ?? []" :key="section.nama" class="mb-4">
                <div class="mb-1 text-xs font-bold uppercase tracking-wider text-[#8a9690]">{{ section.nama }}</div>
                <div v-for="row in section.items ?? []" :key="row.nama"
                     class="flex justify-between border-b border-[#ece3cf] px-1 py-2 text-sm">
                    <span>{{ row.nama }}</span>
                    <span class="font-semibold" :class="row.saldo < 0 ? 'text-rose-600' : ''">{{ formatRupiahFull(row.saldo) }}</span>
                </div>
            </div>

            <div v-if="data.netCashFlow != null"
                 class="mb-5 rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-4 text-center text-white"
                 style="border: 1px solid rgba(201,162,74,0.3);">
                <div class="text-[11px] uppercase tracking-wider text-[#e7d3a1]">Arus Kas Bersih</div>
                <div class="font-['Marcellus',serif] text-xl">{{ formatRupiahFull(data.netCashFlow) }}</div>
            </div>

            <!-- Tutup Buku -->
            <section v-if="props.can?.close_period" class="rounded-xl border border-[#ece3cf] bg-white p-4">
                <h2 class="mb-2 font-['Marcellus',serif] text-base text-[#0A5C36]">Tutup Buku</h2>

                <div v-if="!tutup" class="text-sm text-[#8a9690]">Memuat pratinjau…</div>

                <div v-else-if="!tutup.periode" class="text-sm text-[#8a9690]">
                    Tidak ada periode akuntansi yang terbuka.
                </div>

                <template v-else>
                    <div class="mb-3 space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#8a9690]">Periode</span>
                            <span class="font-semibold">{{ tutup.periode }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#8a9690]">Rentang</span>
                            <span>{{ formatTanggal(tutup.tgl_mulai) }} – {{ formatTanggal(tutup.tgl_selesai) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#8a9690]">Surplus / Defisit</span>
                            <span class="font-bold" :class="tutup.surplus_defisit >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                {{ formatRupiahFull(tutup.surplus_defisit) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#8a9690]">Baris jurnal penutup</span>
                            <span>{{ tutup.jumlah_baris }}</span>
                        </div>
                    </div>

                    <div v-if="tutupPesan" class="mb-3 rounded-lg px-3 py-2 text-sm"
                         :class="tutupPesan.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                        {{ tutupPesan.teks }}
                    </div>

                    <button v-if="!tutup.sudah_ditutup"
                            @click="kunciPeriode" :disabled="tutupLoading"
                            class="w-full rounded-xl bg-[#0A5C36] py-3 text-sm font-bold text-white transition hover:bg-[#084a2c] disabled:opacity-50">
                        {{ tutupLoading ? 'Memproses…' : '🔒 Kunci Periode ' + tutup.periode }}
                    </button>
                    <div v-else class="rounded-lg bg-emerald-50 px-3 py-2 text-center text-sm font-semibold text-emerald-700">
                        ✓ Periode sudah ditutup
                    </div>

                    <p class="mt-2 text-[11px] leading-relaxed text-[#8a9690]">
                        Tutup buku memposting jurnal penutup (pendapatan & beban → Aset Neto) lalu mengunci periode dari posting baru. Pembukaan kembali hanya lewat panel admin.
                    </p>
                </template>
            </section>
        </template>
    </div>
</template>
