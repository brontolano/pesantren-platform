<?php

namespace Modules\Notifikasi\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Notifikasi\Models\TemplateNotifikasi;

/**
 * Seeder template notifikasi WA default.
 * Jalankan dalam konteks tenant (tenancy()->initialize($tenant) dulu).
 *
 * Penggunaan:
 *   tenancy()->initialize($tenant);
 *   (new TemplateNotifikasiSeeder())->run();
 *   tenancy()->end();
 *
 * Atau via artisan:
 *   php artisan db:seed --class="Modules\Notifikasi\Database\Seeders\TemplateNotifikasiSeeder"
 */
class TemplateNotifikasiSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ── Tagihan & Pembayaran ────────────────────────────────────────
            [
                'kode_template' => 'TAGIHAN_BARU',
                'judul' => 'Pemberitahuan Tagihan Baru',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'jatuh_tempo', 'nama_pesantren'],
                'isi' => <<<'MSG'
Assalamu'alaikum Bapak/Ibu Wali Santri {{nama_santri}},

Kami informasikan bahwa telah terbit tagihan baru:

📋 Jenis   : {{jenis_tagihan}}
💰 Nominal : Rp {{nominal}}
📅 Jatuh Tempo : {{jatuh_tempo}}

Mohon segera melakukan pembayaran sebelum jatuh tempo.

Informasi lebih lanjut, silakan hubungi bendahara {{nama_pesantren}}.

Jazakumullahu khairan.
MSG,
            ],

            [
                'kode_template' => 'TAGIHAN_JATUH_TEMPO',
                'judul' => 'Pengingat Tagihan Akan Jatuh Tempo',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'jatuh_tempo', 'nama_pesantren'],
                'isi' => <<<'MSG'
⚠️ *PENGINGAT TAGIHAN*

Yth. Wali Santri {{nama_santri}},

Tagihan *{{jenis_tagihan}}* senilai *Rp {{nominal}}* akan jatuh tempo pada *{{jatuh_tempo}}*.

Mohon segera diselesaikan untuk menghindari keterlambatan.

Terima kasih atas perhatiannya.
— {{nama_pesantren}}
MSG,
            ],

            [
                'kode_template' => 'PEMBAYARAN_DITERIMA',
                'judul' => 'Konfirmasi Pembayaran Diterima',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'tanggal_bayar', 'nama_pesantren'],
                'isi' => <<<'MSG'
✅ *PEMBAYARAN DITERIMA*

Assalamu'alaikum,

Pembayaran Anda telah kami terima:

👤 Santri   : {{nama_santri}}
📋 Tagihan  : {{jenis_tagihan}}
💰 Nominal  : Rp {{nominal}}
📅 Tanggal  : {{tanggal_bayar}}

Jazakumullahu khairan atas kepercayaan Bapak/Ibu.
— {{nama_pesantren}}
MSG,
            ],

            [
                'kode_template' => 'TAGIHAN_TERLAMBAT',
                'judul' => 'Pemberitahuan Tagihan Melewati Jatuh Tempo',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'jatuh_tempo', 'hari_terlambat', 'nama_pesantren'],
                'isi' => <<<'MSG'
🔴 *TAGIHAN MELEWATI JATUH TEMPO*

Yth. Wali Santri {{nama_santri}},

Tagihan *{{jenis_tagihan}}* senilai *Rp {{nominal}}* telah melewati jatuh tempo {{jatuh_tempo}} ({{hari_terlambat}} hari).

Mohon segera menghubungi bendahara {{nama_pesantren}} untuk penyelesaian.

Terima kasih.
MSG,
            ],

            // ── PPDB ───────────────────────────────────────────────────────
            [
                'kode_template' => 'PPDB_PENDAFTARAN_DITERIMA',
                'judul' => 'Konfirmasi Pendaftaran PPDB Diterima',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_calon', 'nomor_pendaftaran', 'nama_pesantren', 'tahun_ajaran'],
                'isi' => <<<'MSG'
✅ *PENDAFTARAN DITERIMA*

Assalamu'alaikum,

Pendaftaran PPDB {{nama_pesantren}} Tahun Ajaran {{tahun_ajaran}} telah kami terima.

👤 Nama         : {{nama_calon}}
📋 No. Daftar   : {{nomor_pendaftaran}}

Kami akan menginformasikan jadwal seleksi selanjutnya.

Jazakumullahu khairan.
MSG,
            ],

            [
                'kode_template' => 'PPDB_DITERIMA',
                'judul' => 'Selamat — Calon Santri Diterima',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_calon', 'nomor_pendaftaran', 'nama_pesantren', 'batas_registrasi_ulang'],
                'isi' => <<<'MSG'
🎉 *SELAMAT! CALON SANTRI DITERIMA*

Assalamu'alaikum Bapak/Ibu,

Dengan penuh syukur kami sampaikan bahwa:

👤 *{{nama_calon}}*
📋 No. Daftar: {{nomor_pendaftaran}}

*DITERIMA* sebagai santri baru di {{nama_pesantren}}.

Mohon segera melakukan *registrasi ulang* paling lambat:
📅 {{batas_registrasi_ulang}}

Informasi registrasi ulang akan dikirimkan terpisah.

Barakallahu fiikum.
MSG,
            ],

            [
                'kode_template' => 'PPDB_TIDAK_DITERIMA',
                'judul' => 'Hasil Seleksi PPDB',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_calon', 'nomor_pendaftaran', 'nama_pesantren'],
                'isi' => <<<'MSG'
Assalamu'alaikum Bapak/Ibu,

Kami sampaikan hasil seleksi PPDB {{nama_pesantren}}:

👤 {{nama_calon}} (No. Daftar: {{nomor_pendaftaran}})

Mohon maaf, pada tahap seleksi ini putra/putri Bapak/Ibu belum dapat kami terima. Kami berharap ini menjadi motivasi untuk terus berkembang.

Jika ada pertanyaan, silakan hubungi kami.

Jazakumullahu khairan.
MSG,
            ],

            // ── Santri & Kegiatan ──────────────────────────────────────────
            [
                'kode_template' => 'IZIN_DISETUJUI',
                'judul' => 'Izin Santri Disetujui',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'jenis_izin', 'tanggal_mulai', 'tanggal_selesai', 'nama_pesantren'],
                'isi' => <<<'MSG'
✅ *IZIN DISETUJUI*

Assalamu'alaikum,

Pengajuan izin berikut telah *disetujui*:

👤 Santri     : {{nama_santri}}
📋 Jenis Izin : {{jenis_izin}}
📅 Tanggal    : {{tanggal_mulai}} s/d {{tanggal_selesai}}

Mohon santri kembali tepat waktu sesuai izin yang diberikan.

— {{nama_pesantren}}
MSG,
            ],

            // WP-29 — Pos Gerbang: santri keluar tanpa izin (notif pengasuhan).
            [
                'kode_template' => 'GERBANG_TANPA_IZIN',
                'judul' => 'Santri Keluar Tanpa Izin',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'nis', 'waktu', 'petugas'],
                'isi' => <<<'MSG'
🚨 *SANTRI KELUAR TANPA IZIN*

Tercatat di pos gerbang:

👤 Santri  : {{nama_santri}} (NIS {{nis}})
🕐 Waktu   : {{waktu}}
🛡️ Petugas : {{petugas}}

Mohon segera ditindaklanjuti oleh pengasuhan.
MSG,
            ],

            [
                'kode_template' => 'PENGUMUMAN_UMUM',
                'judul' => 'Pengumuman dari Pesantren',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_pesantren', 'judul_pengumuman', 'isi_pengumuman'],
                'isi' => <<<'MSG'
📢 *PENGUMUMAN*
{{nama_pesantren}}

*{{judul_pengumuman}}*

{{isi_pengumuman}}

Terima kasih atas perhatiannya.
MSG,
            ],

            // ── F3-PWA Pesan Wali ───────────────────────────────────────────
            [
                'kode_template' => 'PESAN_WALI_BARU',
                'judul' => 'Pesan Baru dari Guru',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'nama_guru', 'jenis', 'isi_pesan', 'nama_pesantren'],
                'isi' => <<<'MSG'
✉️ *PESAN DARI GURU*

Assalamu'alaikum Bapak/Ibu Wali Santri {{nama_santri}},

Ada pesan ({{jenis}}) dari {{nama_guru}}:

{{isi_pesan}}

Pesan ini juga tersedia di Portal Wali.
— {{nama_pesantren}}
MSG,
            ],

            // ── WP-16 Kontrak & SK ──────────────────────────────────────────
            [
                'kode_template' => 'KONTRAK_AKAN_HABIS',
                'judul' => 'Kontrak/SK Pegawai Akan Habis',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_pegawai', 'jenis', 'nomor', 'tanggal_selesai', 'sisa_hari', 'nama_pesantren'],
                'isi' => <<<'MSG'
⏰ *KONTRAK/SK AKAN HABIS*

Masa berlaku dokumen berikut segera berakhir:

👤 Pegawai : {{nama_pegawai}}
📄 Jenis   : {{jenis}}
🔢 Nomor   : {{nomor}}
📅 Berakhir: {{tanggal_selesai}} ({{sisa_hari}} hari lagi)

Mohon segera proses perpanjangan atau penyesuaian status di menu Kontrak & SK.

— {{nama_pesantren}}
MSG,
            ],

            // ── Event uang PWA Wali/Peserta (NOTIF-1, TGH-5, TAB-4) ─────────
            [
                'kode_template' => 'PEMBAYARAN_DIVERIFIKASI',
                'judul' => 'Pembayaran Diterima',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'status_tagihan', 'nama_pesantren'],
                'isi' => <<<'MSG'
✅ *PEMBAYARAN DITERIMA*

Yth. Wali Santri {{nama_santri}},

Pembayaran *{{jenis_tagihan}}* sebesar *Rp {{nominal}}* telah kami VERIFIKASI.
Status tagihan saat ini: *{{status_tagihan}}*.

Jazakumullahu khairan — {{nama_pesantren}}.
MSG,
            ],

            [
                'kode_template' => 'PEMBAYARAN_DITOLAK',
                'judul' => 'Bukti Pembayaran Ditolak',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'alasan', 'nama_pesantren'],
                'isi' => <<<'MSG'
⚠️ *BUKTI PEMBAYARAN DITOLAK*

Yth. Wali Santri {{nama_santri}},

Mohon maaf, bukti pembayaran *{{jenis_tagihan}}* sebesar *Rp {{nominal}}* belum dapat kami terima.
Alasan: {{alasan}}

Silakan unggah ulang bukti yang benar melalui aplikasi, atau hubungi bendahara {{nama_pesantren}}.
MSG,
            ],

            [
                'kode_template' => 'TOPUP_DIVERIFIKASI',
                'judul' => 'Setoran Saldo Berhasil',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'nominal', 'saldo', 'nama_pesantren'],
                'isi' => <<<'MSG'
💰 *SETORAN SALDO BERHASIL*

Yth. Wali Santri {{nama_santri}},

Setoran saldo tabungan sebesar *Rp {{nominal}}* telah kami verifikasi.
Saldo tabungan saat ini: *Rp {{saldo}}*.

Jazakumullahu khairan — {{nama_pesantren}}.
MSG,
            ],

            [
                'kode_template' => 'TOPUP_DITOLAK',
                'judul' => 'Setoran Saldo Ditolak',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'nominal', 'alasan', 'nama_pesantren'],
                'isi' => <<<'MSG'
⚠️ *SETORAN SALDO DITOLAK*

Yth. Wali Santri {{nama_santri}},

Mohon maaf, pengajuan setor saldo sebesar *Rp {{nominal}}* belum dapat kami terima.
Alasan: {{alasan}}

Silakan ajukan ulang dengan bukti transfer yang benar — {{nama_pesantren}}.
MSG,
            ],

            [
                'kode_template' => 'BUKTI_BAYAR_MASUK',
                'judul' => 'Antrean Verifikasi Baru',
                'channel' => 'wa',
                'variable_dinamis' => ['jenis', 'nama_santri', 'nominal', 'nama_pesantren'],
                'isi' => <<<'MSG'
🔔 *ANTRIAN VERIFIKASI BARU* — {{nama_pesantren}}

Jenis: {{jenis}}
Santri: {{nama_santri}}
Nominal: Rp {{nominal}}

Mohon segera diverifikasi melalui panel admin.
MSG,
            ],

            [
                'kode_template' => 'BERKAS_PPDB_MASUK',
                'judul' => 'Berkas/Bukti PPDB Baru',
                'channel' => 'wa',
                'variable_dinamis' => ['jenis', 'nama_calon', 'nama_pesantren'],
                'isi' => <<<'MSG'
🔔 *BERKAS PPDB BARU* — {{nama_pesantren}}

{{jenis}} dari calon santri *{{nama_calon}}* baru diunggah dan menunggu verifikasi panitia.

Mohon diperiksa melalui panel admin.
MSG,
            ],

            [
                'kode_template' => 'PENGINGAT_DAFTAR_ULANG',
                'judul' => 'Pengingat Daftar Ulang',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_calon', 'nomor_pendaftaran', 'nama_pesantren'],
                'isi' => <<<'MSG'
🎉 *PENGINGAT DAFTAR ULANG*

Assalamu'alaikum Bapak/Ibu,

Calon santri *{{nama_calon}}* (No. Daftar {{nomor_pendaftaran}}) telah *LULUS* seleksi {{nama_pesantren}} namun *belum melakukan daftar ulang*.

Mohon segera menyelesaikan daftar ulang melalui aplikasi peserta agar kursi tidak hangus.

Jazakumullahu khairan.
MSG,
            ],

            [
                'kode_template' => 'KREDENSIAL_SANTRI',
                'judul' => 'Akun Aplikasi Santri',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'nis', 'username', 'password', 'nama_pesantren'],
                'isi' => <<<'MSG'
🔐 *AKUN APLIKASI SANTRI* — {{nama_pesantren}}

Assalamu'alaikum Bapak/Ibu Wali Santri *{{nama_santri}}* (NIS {{nis}}),

Akun aplikasi santri telah dibuat:
👤 Username : {{username}}
🔑 Password sementara : {{password}}

Mohon segera login dan GANTI password. Jaga kerahasiaan kredensial ini.

Jazakumullahu khairan.
MSG,
            ],
            // ── Disiplin (WP-09) ───────────────────────────────────────────
            [
                'kode_template' => 'DISIPLIN_PELANGGARAN',
                'judul' => 'Pemberitahuan Catatan Disiplin Santri',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'jenis_pelanggaran', 'kategori', 'poin', 'waktu', 'nama_pesantren'],
                'isi' => <<<'MSG'
Assalamu'alaikum Bapak/Ibu Wali Santri {{nama_santri}},

Kami informasikan catatan kedisiplinan ananda yang telah diverifikasi:

📋 Jenis : {{jenis_pelanggaran}} ({{kategori}})
🔢 Poin  : {{poin}}
🕐 Waktu : {{waktu}}

Mohon menjadi perhatian bersama untuk pembinaan ananda.

Jazakumullahu khairan.
— {{nama_pesantren}}
MSG,
            ],
            [
                'kode_template' => 'DISIPLIN_ESKALASI',
                'judul' => 'Pemberitahuan Surat Peringatan Disiplin',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'label', 'total_poin', 'periode', 'nama_pesantren'],
                'isi' => <<<'MSG'
⚠️ *PEMBERITAHUAN {{label}}*

Yth. Wali Santri {{nama_santri}},

Akumulasi poin kedisiplinan ananda pada periode {{periode}} telah mencapai *{{total_poin}} poin* sehingga memasuki jenjang *{{label}}*.

Mohon kesediaan Bapak/Ibu berkomunikasi dengan bagian pengasuhan untuk pembinaan lebih lanjut.

Jazakumullahu khairan.
— {{nama_pesantren}}
MSG,
            ],
            // ── 2a Dasbor Mutu ────────────────────────────────────────────
            [
                'kode_template' => 'MUTU_LAMPU_KUNING',
                'judul' => 'Lampu Kuning Mutu Santri',
                'channel' => 'wa',
                'variable_dinamis' => ['nama_santri', 'nis', 'jenis', 'keterangan', 'nama_pesantren'],
                'isi' => <<<'MSG'
🟡 *LAMPU KUNING MUTU SANTRI*

Santri : {{nama_santri}} ({{nis}})
Perhatian: {{keterangan}}

Mohon perhatian dan pendampingan untuk ananda. Detail lengkap dapat dilihat pada Dasbor Mutu Santri.

— {{nama_pesantren}}
MSG,
            ],
        ];

        $tenantId = tenant('id') ?? throw new \RuntimeException('Seeder harus dijalankan dalam konteks tenant.');

        foreach ($templates as $template) {
            TemplateNotifikasi::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'kode_template' => $template['kode_template'],
                ],
                [
                    'judul' => $template['judul'],
                    'isi' => $template['isi'],
                    'channel' => $template['channel'],
                    'variable_dinamis' => $template['variable_dinamis'],
                    'is_active' => true,
                ]
            );
        }

        $count = count($templates);
        echo "  ✅ {$count} template notifikasi WA berhasil di-seed untuk tenant: {$tenantId}\n";
    }
}
