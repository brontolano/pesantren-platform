<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, MoneyText, IconCircle, BottomSheet, UiButton, UiField, UiInput } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    periode: { type: String, default: '' },
    slip: { type: Array, default: () => [] },
})

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || ''
}

const prosesOpen = ref(false)
const periodeInput = ref(props.periode || '')
const memproses = ref(false)
const hasilProses = ref(null)
const errorProses = ref('')

async function prosesPayroll() {
    if (!periodeInput.value) return
    memproses.value = true
    errorProses.value = ''
    hasilProses.value = null
    try {
        const res = await fetch('/keuangan/api/payroll/proses', {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ periode_id: periodeInput.value }),
        })
        const j = await res.json().catch(() => ({}))
        if (res.ok) {
            hasilProses.value = j.ringkasan
        } else {
            errorProses.value = j.message || 'Gagal memproses payroll.'
        }
    } catch (e) {
        errorProses.value = 'Tidak ada koneksi.'
    } finally {
        memproses.value = false
    }
}

function tutupSetelahProses() {
    prosesOpen.value = false
    hasilProses.value = null
    router.reload({ only: ['periode', 'slip'] })
}

// final -> 'lunas' (emerald), draft -> 'menunggu' (amber); label di-override.
const statusKey = (s) => (s === 'final' ? 'lunas' : 'menunggu')
const statusLabel = (s) => (s === 'final' ? 'Final' : 'Draft')

const sheetOpen = ref(false)
const selected = ref(null)

function openDetail(s) {
    selected.value = s
    sheetOpen.value = true
}

const totalNeto = computed(() => props.slip.reduce((sum, s) => sum + (s.neto ?? 0), 0))
</script>

<template>
    <Head title="Slip Gaji" />

    <AppShell :nav-items="navigation" brand-name="HRD Kepegawaian">
        <div class="space-y-5">
            <PageHeader kicker="Penggajian" title="Slip Gaji" :subtitle="`Periode ${periode}`">
                <template v-if="can.proses_payroll" #right>
                    <UiButton variant="primary" size="sm" icon-left="check" @click="prosesOpen = true">
                        Proses Payroll
                    </UiButton>
                </template>
            </PageHeader>

            <SectionCard kicker="Ringkasan" title="Total Neto Periode Ini">
                <MoneyText :amount="totalNeto" size="xl" tone="emerald" />
                <p class="mt-1 text-xs font-medium text-slate-400">{{ slip.length }} slip pegawai</p>
            </SectionCard>

            <SectionCard flush>
                <div class="divide-y divide-slate-100">
                    <button
                        v-for="s in slip"
                        :key="s.id"
                        type="button"
                        class="block w-full text-left"
                        @click="openDetail(s)"
                    >
                        <ListRow :title="s.nama" :subtitle="s.jabatan" chevron>
                            <template #leading><IconCircle icon="receipt" tone="blue" /></template>
                            <template #trailing>
                                <MoneyText :amount="s.neto" tone="emerald" size="md" />
                                <StatusBadge :status="statusKey(s.status)" :label="statusLabel(s.status)" size="sm" class="mt-1" />
                            </template>
                        </ListRow>
                    </button>
                </div>
            </SectionCard>
        </div>

        <BottomSheet v-model="sheetOpen" title="Rincian Slip Gaji">
            <div v-if="selected" class="space-y-4">
                <div>
                    <p class="text-base font-bold text-slate-900">{{ selected.nama }}</p>
                    <p class="text-sm text-slate-500">{{ selected.jabatan }} · {{ periode }}</p>
                    <StatusBadge :status="statusKey(selected.status)" :label="statusLabel(selected.status)" size="sm" class="mt-2" />
                </div>

                <div class="space-y-3 rounded-2xl bg-slate-50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Gaji Bruto</span>
                        <MoneyText :amount="selected.bruto" size="md" />
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Tunjangan</span>
                        <MoneyText :amount="selected.tunjangan" size="md" tone="emerald" />
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Potongan</span>
                        <MoneyText :amount="-selected.potongan" size="md" tone="rose" signed />
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-200 pt-3">
                        <span class="text-sm font-bold text-slate-900">Gaji Neto</span>
                        <MoneyText :amount="selected.neto" size="lg" tone="emerald" />
                    </div>
                </div>
            </div>

            <template #footer>
                <UiButton variant="secondary" block @click="sheetOpen = false">Tutup</UiButton>
            </template>
        </BottomSheet>

        <BottomSheet v-model="prosesOpen" title="Proses Payroll">
            <div v-if="!hasilProses" class="space-y-3">
                <p class="text-sm leading-relaxed text-slate-600">
                    Terbitkan slip gaji untuk semua pegawai aktif pada periode ini. Slip yang sudah ada tidak
                    dibuat ulang (aman dijalankan berkali-kali).
                </p>
                <UiField label="Periode (YYYY-MM)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            v-model="periodeInput"
                            type="text"
                            placeholder="2026-07"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>
                <p v-if="errorProses" class="rounded-2xl bg-rose-50 px-3.5 py-2.5 text-xs text-rose-700 ring-1 ring-inset ring-rose-100">
                    {{ errorProses }}
                </p>
            </div>
            <div v-else class="space-y-3">
                <div class="rounded-2xl bg-emerald-50 px-4 py-3 ring-1 ring-inset ring-emerald-100">
                    <p class="text-sm font-bold text-emerald-800">{{ hasilProses.slip_final }} slip final diterbitkan</p>
                    <p class="mt-1 text-xs text-emerald-700">
                        {{ hasilProses.slip_dibuat_baru }} baru · {{ hasilProses.slip_sudah_ada }} sudah ada sebelumnya
                    </p>
                </div>
                <div v-if="hasilProses.gagal?.length" class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-rose-500">Gagal ({{ hasilProses.gagal.length }})</p>
                    <div v-for="(g, i) in hasilProses.gagal" :key="i" class="rounded-xl bg-rose-50 px-3.5 py-2.5 text-xs text-rose-700 ring-1 ring-inset ring-rose-100">
                        <strong>{{ g.pegawai }}</strong>: {{ g.error }}
                    </div>
                </div>
            </div>
            <template #footer>
                <div v-if="!hasilProses" class="flex gap-2">
                    <UiButton variant="ghost" block :disabled="memproses" @click="prosesOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block :disabled="memproses || !periodeInput" @click="prosesPayroll">
                        {{ memproses ? 'Memproses…' : 'Proses Sekarang' }}
                    </UiButton>
                </div>
                <UiButton v-else variant="primary" block @click="tutupSetelahProses">Selesai</UiButton>
            </template>
        </BottomSheet>
    </AppShell>
</template>
