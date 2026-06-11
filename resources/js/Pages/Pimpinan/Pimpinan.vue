<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'

defineProps({ userName: String })

const data = ref(null)
const error = ref('')
const busy = ref({})
const tolakId = ref(null)
const alasan = ref('')

function rp(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID')
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || ''
}

async function muat() {
    try {
        const res = await fetch('/pimpinan/api/ringkasan', {
            headers: { Accept: 'application/json' }, credentials: 'same-origin',
        })
        if (res.ok) data.value = await res.json()
        else error.value = 'Gagal memuat data.'
    } catch (e) { error.value = 'Tidak ada koneksi.' }
}

async function putuskan(id, aksi) {
    busy.value[id] = true
    try {
        const body = aksi === 'tolak' ? JSON.stringify({ alasan: alasan.value }) : '{}'
        const res = await fetch(`/pimpinan/api/approval/${id}/${aksi}`, {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body,
        })
        if (res.ok) {
            tolakId.value = null
            alasan.value = ''
            await muat()
        } else {
            const j = await res.json().catch(() => ({}))
            error.value = j.message || 'Gagal memproses.'
        }
    } catch (e) { error.value = 'Tidak ada koneksi.' }
    busy.value[id] = false
}

onMounted(muat)
</script>

<template>
    <Head title="Dasbor Pimpinan" />
    <div class="min-h-screen bg-[#FDFBF7] p-4 max-w-lg mx-auto">
        <h1 class="text-lg font-semibold text-[#0A5C36]">Dasbor Pimpinan</h1>
        <p class="text-xs text-gray-500 mb-3">Assalamu'alaikum, {{ userName }}</p>

        <p v-if="error" class="text-sm text-red-600 mb-2">{{ error }}</p>

        <div v-if="data" class="space-y-4">
            <!-- Kas hari ini -->
            <section class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-white border p-4 col-span-2">
                    <div class="text-xs text-gray-500">Saldo Kas</div>
                    <div class="text-xl font-semibold">{{ rp(data.kas.saldo) }}</div>
                </div>
                <div class="rounded-xl bg-white border p-4">
                    <div class="text-xs text-gray-500">Pemasukan</div>
                    <div class="font-semibold">{{ rp(data.kas.pendapatan) }}</div>
                </div>
                <div class="rounded-xl bg-white border p-4">
                    <div class="text-xs text-gray-500">Pengeluaran</div>
                    <div class="font-semibold">{{ rp(data.kas.beban) }}</div>
                </div>
            </section>

            <!-- Kehadiran hari ini -->
            <section class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-white border p-4">
                    <div class="text-xs text-gray-500">Kehadiran Santri Hari Ini</div>
                    <div class="text-xl font-semibold">
                        {{ data.kehadiran.santri.persen === null ? '—' : data.kehadiran.santri.persen + '%' }}
                    </div>
                    <div class="text-xs text-gray-400">{{ data.kehadiran.santri.hadir }}/{{ data.kehadiran.santri.total }} catatan</div>
                </div>
                <div class="rounded-xl bg-white border p-4">
                    <div class="text-xs text-gray-500">Kehadiran Guru Hari Ini</div>
                    <div class="text-xl font-semibold">
                        {{ data.kehadiran.guru.persen === null ? '—' : data.kehadiran.guru.persen + '%' }}
                    </div>
                    <div class="text-xs text-gray-400">{{ data.kehadiran.guru.hadir }}/{{ data.kehadiran.guru.total }} catatan</div>
                </div>
            </section>

            <!-- Lampu mutu (defensif — tampil hanya bila modul mutu siap) -->
            <section v-if="data.mutu" class="rounded-xl bg-white border p-4">
                <div class="text-xs text-gray-500 mb-2">Lampu Mutu</div>
                <div class="flex gap-4">
                    <span v-for="(jumlah, warna) in data.mutu" :key="warna" class="text-sm">
                        <span :class="{
                            'text-green-700': warna === 'hijau',
                            'text-amber-600': warna === 'kuning',
                            'text-red-600': warna === 'merah',
                        }">●</span> {{ warna }}: <b>{{ jumlah }}</b>
                    </span>
                </div>
            </section>

            <!-- Antrean lain (baca-saja) -->
            <section class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-white border p-4">
                    <div class="text-xs text-gray-500">Izin Santri Menunggu</div>
                    <div class="text-xl font-semibold">{{ data.antrean.izin_diajukan }}</div>
                </div>
                <div class="rounded-xl bg-white border p-4">
                    <div class="text-xs text-gray-500">Pelanggaran Belum Diverifikasi</div>
                    <div class="text-xl font-semibold">{{ data.antrean.pelanggaran_menunggu }}</div>
                </div>
            </section>

            <!-- Antrean approval (hanya rule yang user-nya approver) -->
            <section class="rounded-xl bg-white border p-4">
                <div class="text-xs text-gray-500 mb-2">Menunggu Persetujuan Anda</div>
                <p v-if="data.antrean.approval.length === 0" class="text-sm text-gray-400">Tidak ada antrean.</p>
                <div v-for="item in data.antrean.approval" :key="item.id" class="border-t py-2 first:border-t-0">
                    <div class="text-sm font-medium">{{ item.tipe }} #{{ item.approvable_id }}</div>
                    <div class="text-xs text-gray-400">{{ item.diajukan_pada }}</div>
                    <div class="flex gap-2 mt-2" v-if="tolakId !== item.id">
                        <button class="rounded-lg bg-[#0A5C36] text-white text-sm px-3 py-1.5 disabled:opacity-50"
                            :disabled="busy[item.id]" @click="putuskan(item.id, 'setujui')">Setujui</button>
                        <button class="rounded-lg border border-red-300 text-red-700 text-sm px-3 py-1.5"
                            :disabled="busy[item.id]" @click="tolakId = item.id">Tolak…</button>
                    </div>
                    <div v-else class="mt-2 space-y-2">
                        <input v-model="alasan" placeholder="Alasan penolakan (wajib)"
                            class="w-full rounded-lg border px-3 py-1.5 text-sm" />
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-red-600 text-white text-sm px-3 py-1.5 disabled:opacity-50"
                                :disabled="busy[item.id] || !alasan.trim()" @click="putuskan(item.id, 'tolak')">Kirim Penolakan</button>
                            <button class="rounded-lg border text-sm px-3 py-1.5" @click="tolakId = null; alasan = ''">Batal</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <p v-else-if="!error" class="text-sm text-gray-400">Memuat...</p>
    </div>
</template>
