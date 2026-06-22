<script setup>
/**
 * CONTOH POLA 3-LAPIS (inert — tidak di-route; salin jadi Financial.vue saat siap).
 *
 * Perhatikan: TIDAK ada Intl, filter, fetch, atau aturan bisnis di sini. Semua itu
 * pindah ke composable useFinancial. Halaman ini MURNI menyusun komponen Components/Ui
 * dari data yang sudah jadi. Inilah "UI template" yang bersih & mudah direvisi.
 *
 * Lihat: docs/PLAYBOOK-IRISAN.md · docs/KONVENSI.md · resources/js/types/wali.d.ts
 */
import PortalWaliLayout from '@/Layouts/PortalWaliLayout.vue'
import { PageHeading, SectionCard, StatCard, StatusBadge, MoneyText, EmptyState, UiButton } from '@/Components/Ui'
import { useFinancial } from '@/composables/wali/useFinancial'

defineOptions({ layout: PortalWaliLayout })

const props = defineProps({
  child: { type: Object, required: true },
  bills: { type: Array, default: () => [] },
  payments: { type: Array, default: () => [] },
  savings: { type: Object, required: true },
  summary: { type: Object, required: true },
})

// Seluruh logika datang dari satu pintu:
const f = useFinancial({ ...props, childId: props.child.id })

const TABS = [
  { key: 'tagihan', label: 'Tagihan' },
  { key: 'tabungan', label: 'Tabungan' },
  { key: 'riwayat', label: 'Riwayat' },
]
</script>

<template>
  <div class="space-y-4">
    <PageHeading kicker="Keuangan" :title="child.nama" :subtitle="child.kelas" />

    <!-- Ringkasan (StatCard bermerek, bukan div Tailwind mentah) -->
    <div class="grid grid-cols-2 gap-3">
      <StatCard icon="receipt-text" tone="rose" label="Tagihan belum lunas" :value="f.summary.value.tagihanBelumLunas" />
      <StatCard icon="wallet" tone="emerald" label="Total tunggakan" :value="f.fmt(f.summary.value.totalOutstanding)" />
    </div>

    <!-- Tab -->
    <div class="flex gap-2">
      <button
        v-for="t in TABS" :key="t.key"
        class="rounded-full px-4 py-2 text-[13px] font-semibold transition"
        :class="f.activeTab.value === t.key ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 shadow-[inset_0_0_0_1px_#efe7da]'"
        @click="f.activeTab.value = t.key"
      >{{ t.label }}</button>
    </div>

    <!-- Tagihan belum lunas -->
    <SectionCard v-if="f.activeTab.value === 'tagihan'" title="Belum dibayar">
      <EmptyState v-if="!f.unpaidBills.value.length" icon="check-circle" tone="emerald"
        title="Semua lunas" message="Tidak ada tagihan tertunggak." />

      <ul v-else class="divide-y divide-[#efe7da]">
        <li v-for="bill in f.unpaidBills.value" :key="bill.id" class="flex items-center justify-between gap-3 py-3">
          <div class="min-w-0">
            <p class="truncate font-jost text-sm font-semibold text-slate-800">{{ bill.jenisTagihan }}</p>
            <p class="text-xs text-slate-500">Jatuh tempo {{ bill.jatuhTempo ?? '—' }}</p>
            <StatusBadge :status="bill.status" size="sm" class="mt-1" />
          </div>
          <div class="flex shrink-0 flex-col items-end gap-2">
            <MoneyText :amount="bill.sisaTagihan" tone="rose" />
            <UiButton size="sm" variant="primary" :loading="f.submitting.value"
              @click="f.bayar(bill.id, { nominal: bill.sisaTagihan, metode: 'transfer' })">
              Bayar
            </UiButton>
          </div>
        </li>
      </ul>
    </SectionCard>

    <!-- Tabungan -->
    <SectionCard v-else-if="f.activeTab.value === 'tabungan'" title="Saldo tabungan">
      <MoneyText :amount="f.saldo.value" size="lg" tone="emerald" />
      <UiButton class="mt-3" block variant="secondary" :loading="f.submitting.value"
        @click="f.topup({ nominal: 50000 })">Topup</UiButton>
    </SectionCard>

    <!-- Riwayat pembayaran -->
    <SectionCard v-else title="Riwayat pembayaran">
      <EmptyState v-if="!payments.length" title="Belum ada pembayaran" />
      <ul v-else class="divide-y divide-[#efe7da]">
        <li v-for="p in payments" :key="p.id" class="flex items-center justify-between py-3">
          <div>
            <p class="font-jost text-sm font-semibold text-slate-800">{{ p.tagihan ?? 'Pembayaran' }}</p>
            <p class="text-xs text-slate-500">{{ p.tanggalPembayaran ?? '—' }} · {{ p.metode ?? '—' }}</p>
          </div>
          <MoneyText :amount="p.nominal" />
        </li>
      </ul>
    </SectionCard>
  </div>
</template>
