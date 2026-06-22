/**
 * LAPISAN FRONTEND LOGIC — layar Keuangan Portal Wali.
 *
 * Semua state turunan + aturan tampil + aksi tinggal di sini. Halaman .vue cukup
 * memanggil composable ini lalu merender hasilnya (template murni). Mengubah logika
 * = sentuh berkas ini saja; mengubah tampilan = sentuh .vue saja. Itulah "pisahkan
 * konteks UI dan frontend logic".
 *
 * @param {{ bills: import('@/types/wali').TagihanWali[], payments: import('@/types/wali').PembayaranWali[], savings: import('@/types/wali').WaliSavings, summary: import('@/types/wali').WaliFinancialSummary, childId: number }} props
 */
import { ref, computed } from 'vue'
import { bayarTagihan as svcBayar, topupTabungan as svcTopup } from '@/services/wali'

const RP = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
})

const STATUS_META = {
  lunas: { label: 'Lunas', tone: 'emerald' },
  sebagian: { label: 'Dibayar sebagian', tone: 'amber' },
  belum_bayar: { label: 'Belum dibayar', tone: 'rose' },
}

export function useFinancial(props) {
  const activeTab = ref('tagihan') // 'tagihan' | 'tabungan' | 'riwayat'
  const submitting = ref(false)

  const unpaidBills = computed(() => props.bills.filter((b) => b.status !== 'lunas'))
  const paidBills = computed(() => props.bills.filter((b) => b.status === 'lunas'))
  const saldo = computed(() => props.savings?.saldo ?? 0)

  const fmt = (v) => RP.format(v || 0)
  const statusMeta = (status) => STATUS_META[status] ?? STATUS_META.belum_bayar

  function bayar(tagihanId, payload) {
    submitting.value = true
    return svcBayar(props.childId, tagihanId, payload, {
      onFinish: () => (submitting.value = false),
    })
  }

  function topup(payload) {
    submitting.value = true
    return svcTopup(props.childId, payload, {
      onFinish: () => (submitting.value = false),
    })
  }

  return {
    // state
    activeTab,
    submitting,
    // derived
    unpaidBills,
    paidBills,
    saldo,
    summary: computed(() => props.summary),
    // helpers
    fmt,
    statusMeta,
    // actions
    bayar,
    topup,
  }
}
