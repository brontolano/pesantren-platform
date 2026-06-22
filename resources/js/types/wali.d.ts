/**
 * KONTRAK frontend Portal Wali — cermin dari Modules/Keuangan/app/Data/*Data.php.
 *
 * Inilah file yang dibaca AI/dev saat menggarap frontend: ringkas, cukup untuk tahu
 * bentuk data TANPA membuka controller/Service PHP. Backend = sumber kebenaran;
 * berkas ini disamakan dengan DTO-nya (manual untuk sekarang; otomatis lewat
 * spatie/typescript-transformer setelah Fase 0).
 *
 * Pakai di JS lewat JSDoc:  /** @type {import('@/types/wali').WaliFinancial} *​/
 */

export type TagihanStatus = 'lunas' | 'sebagian' | 'belum_bayar'

export interface TagihanWali {
  id: number
  jenisTagihan: string
  keterangan: string | null
  nominalAkhir: number
  totalDibayar: number
  sisaTagihan: number
  status: TagihanStatus
  jatuhTempo: string | null        // 'YYYY-MM-DD'
  periodeAkademik: string | null
}

export interface PembayaranWali {
  id: number
  tagihan: string | null
  nominal: number
  metode: string | null
  tanggalPembayaran: string | null  // 'YYYY-MM-DD'
  isVerified: boolean
  nomorBukti: string | null
}

export interface SavingsTransaction {
  id: number
  tipe: string
  nominal: number
  saldoSebelum: number
  saldoSesudah: number
  keterangan: string | null
  tanggal: string | null
}

export interface WaliSavings {
  saldo: number
  transactions: SavingsTransaction[]
}

export interface WaliFinancialSummary {
  tagihanBelumLunas: number
  totalOutstanding: number
}

/** Seluruh props layar PortalWali/Financial.vue. */
export interface WaliFinancial {
  bills: TagihanWali[]
  payments: PembayaranWali[]
  savings: WaliSavings
  summary: WaliFinancialSummary
}
