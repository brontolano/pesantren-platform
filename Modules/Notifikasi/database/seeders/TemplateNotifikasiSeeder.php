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
                'kode_template'    => 'TAGIHAN_BARU',
                'judul'            => 'Pemberitahuan Tagihan Baru',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'jatuh_tempo', 'nama_pesantren'],
                'isi'              => <<<'MSG'
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
                'kode_template'    => 'TAGIHAN_JATUH_TEMPO',
                'judul'            => 'Pengingat Tagihan Akan Jatuh Tempo',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'jatuh_tempo', 'nama_pesantren'],
                'isi'              => <<<'MSG'
⚠️ *PENGINGAT TAGIHAN*

Yth. Wali Santri {{nama_santri}},

Tagihan *{{jenis_tagihan}}* senilai *Rp {{nominal}}* akan jatuh tempo pada *{{jatuh_tempo}}*.

Mohon segera diselesaikan untuk menghindari keterlambatan.

Terima kasih atas perhatiannya.
— {{nama_pesantren}}
MSG,
            ],

            [
                'kode_template'    => 'PEMBAYARAN_DITERIMA',
                'judul'            => 'Konfirmasi Pembayaran Diterima',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'tanggal_bayar', 'nama_pesantren'],
                'isi'              => <<<'MSG'
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
                'kode_template'    => 'TAGIHAN_TERLAMBAT',
                'judul'            => 'Pemberitahuan Tagihan Melewati Jatuh Tempo',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'jatuh_tempo', 'hari_terlambat', 'nama_pesantren'],
                'isi'              => <<<'MSG'
🔴 *TAGIHAN MELEWATI JATUH TEMPO*

Yth. Wali Santri {{nama_santri}},

Tagihan *{{jenis_tagihan}}* senilai *Rp {{nominal}}* telah melewati jatuh tempo {{jatuh_tempo}} ({{hari_terlambat}} hari).

Mohon segera menghubungi bendahara {{nama_pesantren}} untuk penyelesaian.

Terima kasih.
MSG,
            ],

            // ── PPDB ───────────────────────────────────────────────────────
            [
                'kode_template'    => 'PPDB_PENDAFTARAN_DITERIMA',
                'judul'            => 'Konfirmasi Pendaftaran PPDB Diterima',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_calon', 'nomor_pendaftaran', 'nama_pesantren', 'tahun_ajaran'],
                'isi'              => <<<'MSG'
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
                'kode_template'    => 'PPDB_DITERIMA',
                'judul'            => 'Selamat — Calon Santri Diterima',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_calon', 'nomor_pendaftaran', 'nama_pesantren', 'batas_registrasi_ulang'],
                'isi'              => <<<'MSG'
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
                'kode_template'    => 'PPDB_TIDAK_DITERIMA',
                'judul'            => 'Hasil Seleksi PPDB',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_calon', 'nomor_pendaftaran', 'nama_pesantren'],
                'isi'              => <<<'MSG'
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
                'kode_template'    => 'IZIN_DISETUJUI',
                'judul'            => 'Izin Santri Disetujui',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_santri', 'jenis_izin', 'tanggal_mulai', 'tanggal_selesai', 'nama_pesantren'],
                'isi'              => <<<'MSG'
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

            [
                'kode_template'    => 'PENGUMUMAN_UMUM',
                'judul'            => 'Pengumuman dari Pesantren',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_pesantren', 'judul_pengumuman', 'isi_pengumuman'],
                'isi'              => <<<'MSG'
📢 *PENGUMUMAN*
{{nama_pesantren}}

*{{judul_pengumuman}}*

{{isi_pengumuman}}

Terima kasih atas perhatiannya.
MSG,
            ],

            // ── Event uang PWA Wali/Peserta (NOTIF-1, TGH-5, TAB-4) ─────────
            [
                'kode_template'    => 'PEMBAYARAN_DIVERIFIKASI',
                'judul'            => 'Pembayaran Diterima',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'status_tagihan', 'nama_pesantren'],
                'isi'              => <<<'MSG'
✅ *PEMBAYARAN DITERIMA*

Yth. Wali Santri {{nama_santri}},

Pembayaran *{{jenis_tagihan}}* sebesar *Rp {{nominal}}* telah kami VERIFIKASI.
Status tagihan saat ini: *{{status_tagihan}}*.

Jazakumullahu khairan — {{nama_pesantren}}.
MSG,
            ],

            [
                'kode_template'    => 'PEMBAYARAN_DITOLAK',
                'judul'            => 'Bukti Pembayaran Ditolak',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_santri', 'jenis_tagihan', 'nominal', 'alasan', 'nama_pesantren'],
                'isi'              => <<<'MSG'
⚠️ *BUKTI PEMBAYARAN DITOLAK*

Yth. Wali Santri {{nama_santri}},

Mohon maaf, bukti pembayaran *{{jenis_tagihan}}* sebesar *Rp {{nominal}}* belum dapat kami terima.
Alasan: {{alasan}}

Silakan unggah ulang bukti yang benar melalui aplikasi, atau hubungi bendahara {{nama_pesantren}}.
MSG,
            ],

            [
                'kode_template'    => 'TOPUP_DIVERIFIKASI',
                'judul'            => 'Setoran Saldo Berhasil',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_santri', 'nominal', 'saldo', 'nama_pesantren'],
                'isi'              => <<<'MSG'
💰 *SETORAN SALDO BERHASIL*

Yth. Wali Santri {{nama_santri}},

Setoran saldo tabungan sebesar *Rp {{nominal}}* telah kami verifikasi.
Saldo tabungan saat ini: *Rp {{saldo}}*.

Jazakumullahu khairan — {{nama_pesantren}}.
MSG,
            ],

            [
                'kode_template'    => 'TOPUP_DITOLAK',
                'judul'            => 'Setoran Saldo Ditolak',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_santri', 'nominal', 'alasan', 'nama_pesantren'],
                'isi'              => <<<'MSG'
⚠️ *SETORAN SALDO DITOLAK*

Yth. Wali Santri {{nama_santri}},

Mohon maaf, pengajuan setor saldo sebesar *Rp {{nominal}}* belum dapat kami terima.
Alasan: {{alasan}}

Silakan ajukan ulang dengan bukti transfer yang benar — {{nama_pesantren}}.
MSG,
            ],

            [
                'kode_template'    => 'BUKTI_BAYAR_MASUK',
                'judul'            => 'Antrean Verifikasi Baru',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['jenis', 'nama_santri', 'nominal', 'nama_pesantren'],
                'isi'              => <<<'MSG'
🔔 *ANTRIAN VERIFIKASI BARU* — {{nama_pesantren}}

Jenis: {{jenis}}
Santri: {{nama_santri}}
Nominal: Rp {{nominal}}

Mohon segera diverifikasi melalui panel admin.
MSG,
            ],

            [
                'kode_template'    => 'BERKAS_PPDB_MASUK',
                'judul'            => 'Berkas/Bukti PPDB Baru',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['jenis', 'nama_calon', 'nama_pesantren'],
                'isi'              => <<<'MSG'
🔔 *BERKAS PPDB BARU* — {{nama_pesantren}}

{{jenis}} dari calon santri *{{nama_calon}}* baru diunggah dan menunggu verifikasi panitia.

Mohon diperiksa melalui panel admin.
MSG,
            ],

            [
                'kode_template'    => 'PENGINGAT_DAFTAR_ULANG',
                'judul'            => 'Pengingat Daftar Ulang',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_calon', 'nomor_pendaftaran', 'nama_pesantren'],
                'isi'              => <<<'MSG'
🎉 *PENGINGAT DAFTAR ULANG*

Assalamu'alaikum Bapak/Ibu,

Calon santri *{{nama_calon}}* (No. Daftar {{nomor_pendaftaran}}) telah *LULUS* seleksi {{nama_pesantren}} namun *belum melakukan daftar ulang*.

Mohon segera menyelesaikan daftar ulang melalui aplikasi peserta agar kursi tidak hangus.

Jazakumullahu khairan.
MSG,
            ],

            [
                'kode_template'    => 'KREDENSIAL_SANTRI',
                'judul'            => 'Akun Aplikasi Santri',
                'channel'          => 'whatsapp',
                'variable_dinamis' => ['nama_santri', 'nis', 'username', 'password', 'nama_pesantren'],
                'isi'              => <<<'MSG'
🔐 *AKUN APLIKASI SANTRI* — {{nama_pesantren}}

Assalamu'alaikum Bapak/Ibu Wali Santri *{{nama_santri}}* (NIS {{nis}}),

Akun aplikasi santri telah dibuat:
👤 Username : {{username}}
🔑 Password sementara : {{password}}

Mohon segera login dan GANTI password. Jaga kerahasiaan kredensial ini.

Jazakumullahu khairan.
MSG,
            ],
        ];

        $tenantId = tenant('id') ?? throw new \RuntimeException('Seeder harus dijalankan dalam konteks tenant.');

        foreach ($templates as $template) {
            TemplateNotifikasi::updateOrCreate(
                [
                    'tenant_id'     => $tenantId,
                    'kode_template' => $template['kode_template'],
                ],
                [
                    'judul'            => $template['judul'],
                    'isi'              => $template['isi'],
                    'channel'          => $template['channel'],
                    'variable_dinamis' => $template['variable_dinamis'],
                    'is_active'        => true,
                ]
            );
        }

        $count = count($templates);
        echo "  ✅ {$count} template notifikasi WA berhasil di-seed untuk tenant: {$tenantId}\n";
    }
}
