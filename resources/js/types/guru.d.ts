/**
 * KONTRAK frontend Portal Guru — cermin dari app/Data/PortalGuru/*Data.php.
 * Wire-format mengikuti kontrak existing (jam_mulai/jurnal_url snake_case).
 */

export type PresensiStatus = 'hadir' | 'izin' | 'sakit' | 'alpa'

export interface PresensiSantriRow {
  id: number
  nis: string | null
  nama: string
  status: PresensiStatus
}

export interface PresensiSesi {
  id: number
  pelajaran: string | null
  kelas: string | null
  jam_mulai: string | null
  jam_selesai: string | null
}

export interface PresensiJurnal {
  materi: string | null
  metode: string | null
  status: string | null
  pengganti_id: number | null
  jumlah_hadir: number | null
}

/** Props fitur layar PortalGuru/Presensi.vue (di luar sharedProps). */
export interface PresensiPage {
  sesi: PresensiSesi
  tanggal: string
  santri: PresensiSantriRow[]
  statusOptions: PresensiStatus[]
  jurnal: PresensiJurnal | null
  jurnal_url: string
}

/** Body POST /guru/presensi/{jadwal}. */
export interface PresensiSubmit {
  tanggal?: string
  presensi: { santri_id: number; status: PresensiStatus; keterangan?: string }[]
}
