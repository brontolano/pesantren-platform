/**
 * LAPISAN FRONTEND LOGIC — Presensi Portal Guru.
 * State (salinan baris yg bisa diubah), aturan (default hadir, hitung ringkasan),
 * aksi (submit) tinggal di sini. Halaman .vue cukup render + ketuk.
 *
 * @param {{ sesi: import('@/types/guru').PresensiSesi, tanggal: string, santri: import('@/types/guru').PresensiSantriRow[], statusOptions: import('@/types/guru').PresensiStatus[], jadwalId: number }} props
 */
import { ref, computed } from 'vue'
import { submitPresensi } from '@/services/guru'

const STATUS_LABEL = { hadir: 'Hadir', izin: 'Izin', sakit: 'Sakit', alpa: 'Alpa' }

export function usePresensi(props) {
  const submitting = ref(false)
  // Salinan lokal yang bisa diketuk; sumber kebenaran tetap server saat submit.
  const rows = ref(props.santri.map((s) => ({ ...s })))

  const counts = computed(() =>
    rows.value.reduce(
      (acc, r) => ((acc[r.status] = (acc[r.status] ?? 0) + 1), acc),
      { hadir: 0, izin: 0, sakit: 0, alpa: 0 },
    ),
  )
  const total = computed(() => rows.value.length)

  function setStatus(santriId, status) {
    const row = rows.value.find((r) => r.id === santriId)
    if (row) row.status = status
  }

  /** Siklus cepat saat baris diketuk: hadir → izin → sakit → alpa → hadir. */
  function cycle(santriId) {
    const order = props.statusOptions
    const row = rows.value.find((r) => r.id === santriId)
    if (!row) return
    row.status = order[(order.indexOf(row.status) + 1) % order.length]
  }

  function submit(options = {}) {
    submitting.value = true
    return submitPresensi(
      props.jadwalId,
      {
        tanggal: props.tanggal,
        presensi: rows.value.map((r) => ({ santri_id: r.id, status: r.status })),
      },
      { onFinish: () => (submitting.value = false), ...options },
    )
  }

  return { rows, counts, total, submitting, statusLabel: STATUS_LABEL, setStatus, cycle, submit }
}
