<?php

namespace App\Services\PortalWali;

use App\Models\PortalWaliIzin;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Rapor;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\Tagihan;
use Modules\Notifikasi\Models\LogNotifikasi;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Models\PenilaianTahfidz;
use Modules\Kesehatan\Models\RiwayatKesehatan;
use Modules\Kesehatan\Models\SesiKonseling;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Models\KunjunganWali;

class PortalWaliService
{
    public function getAccessibleSantriQuery(User $user): Builder
    {
        return Santri::query()
            ->where(function (Builder $query) use ($user): void {
                $query->where('wali_user_id', $user->getKey())
                    ->orWhereHas('accessWali', fn (Builder $accessQuery) => $accessQuery->where('wali_id', $user->getKey()));
            })
            ->with([
                'accessWali',
                'kelas',
                'penempatanAktif.kelas',
            ]);
    }

    public function getAccessibleSantri(User $user): Collection
    {
        return $this->getAccessibleSantriQuery($user)
            ->orderBy('nama')
            ->get()
            ->map(fn (Santri $santri): array => $this->transformSantriSummary($user, $santri))
            ->values();
    }

    public function findAccessibleSantri(User $user, int|string $santriId): Santri
    {
        return $this->getAccessibleSantriQuery($user)
            ->whereKey($santriId)
            ->firstOrFail();
    }

    public function getDashboardPayload(User $user): array
    {
        $children = $this->getAccessibleSantri($user);

        return [
            'children' => $children,
            'summary' => [
                'childrenCount' => $children->count(),
                'activeChildrenCount' => $children->where('status', 'aktif')->count(),
            ],
        ];
    }

    public function getProfilePayload(User $user, Santri $santri): array
    {
        $santri->loadMissing(['kelas', 'penempatanAktif.kelas']);

        $kelas = $santri->kelas ?? $santri->penempatanAktif?->kelas;

        return [
            'child' => [
                'id' => $santri->id,
                'nis' => $santri->nis,
                'nama' => $santri->nama,
                'photoUrl' => $santri->photo_url,
                'status' => $santri->status,
                'tanggalLahir' => optional($santri->tanggal_lahir)?->format('Y-m-d'),
                'tempatLahir' => $santri->tempat_lahir,
                'jenisKelamin' => $santri->jenis_kelamin,
                'agama' => $santri->agama,
                'asalSekolah' => $santri->asal_sekolah,
                'alamat' => $santri->alamat,
                'tahunAngkatan' => $santri->tahun_angkatan,
                'namaAyah' => $santri->nama_ayah,
                'namaIbu' => $santri->nama_ibu,
                'noHpWali' => $santri->no_hp_wali,
                'emailWali' => $santri->email_wali,
                'kelas' => $kelas ? [
                    'id' => $kelas->id,
                    'nama' => $kelas->nama_kelas,
                    'tingkat' => $kelas->tingkat,
                ] : null,
            ],
            'placementHistory' => $santri->penempatanKelas()
                ->with('kelas')
                ->latest('tanggal_penempatan')
                ->get()
                ->map(fn ($item): array => [
                    'kelas' => $item->kelas?->nama_kelas,
                    'semester' => $item->semester,
                    'tahunAkademik' => $item->tahun_akademik,
                    'tanggalPenempatan' => optional($item->tanggal_penempatan)?->format('Y-m-d'),
                    'isCurrent' => (bool) $item->is_current,
                    'alasanMutasi' => $item->alasan_mutasi,
                ])
                ->values(),
        ];
    }

    public function getAcademicPayload(Santri $santri): array
    {
        $latestRapor = Rapor::query()
            ->where('santri_id', $santri->getKey())
            ->with('periodeAkademik')
            ->latest('generated_at')
            ->first();

        $nilai = Nilai::query()
            ->where('santri_id', $santri->getKey())
            ->with(['pelajaran', 'periodeAkademik'])
            ->orderByDesc('periode_akademik_id')
            ->orderBy('pelajaran_id')
            ->get()
            ->map(fn (Nilai $item): array => [
                'pelajaran' => $item->pelajaran?->nama,
                'kode' => $item->pelajaran?->kode,
                'periode' => $item->periodeAkademik?->nama,
                'nilaiHarian' => $item->nilai_harian,
                'nilaiUts' => $item->nilai_uts,
                'nilaiUas' => $item->nilai_uas,
                'nilaiAkhir' => $item->nilai_akhir,
                'catatan' => $item->catatan,
            ])
            ->values();

        $jadwal = JadwalPelajaran::query()
            ->where('kelas_id', $santri->kelas_id)
            ->with(['pelajaran', 'periodeAkademik'])
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get()
            ->map(fn (JadwalPelajaran $item): array => [
                'hari' => $item->hari,
                'jamMulai' => $item->jam_mulai,
                'jamSelesai' => $item->jam_selesai,
                'pelajaran' => $item->pelajaran?->nama,
                'ruangan' => $item->ruangan,
                'periode' => $item->periodeAkademik?->nama,
            ])
            ->values();

        $izinStats = PortalWaliIzin::query()
            ->where('santri_id', $santri->getKey())
            ->selectRaw("count(*) as total_pengajuan")
            ->selectRaw("sum(case when status = 'approved' then 1 else 0 end) as total_disetujui")
            ->selectRaw("sum(case when status = 'pending' then 1 else 0 end) as total_pending")
            ->first();

        return [
            'latestRapor' => $latestRapor ? [
                'id' => $latestRapor->id,
                'periode' => $latestRapor->periodeAkademik?->nama,
                'tahunAkademik' => $latestRapor->periodeAkademik?->tahun_akademik,
                'semester' => $latestRapor->periodeAkademik?->semester,
                'ipk' => $latestRapor->ipk,
                'totalSks' => $latestRapor->total_sks,
                'rankKelas' => $latestRapor->rank_kelas,
                'generatedAt' => optional($latestRapor->generated_at)?->format('Y-m-d H:i:s'),
                'catatanWaliKelas' => $latestRapor->catatan_walikelas,
                'ringkasanNilai' => $latestRapor->ringkasan_nilai ?? [],
            ] : null,
            'grades' => $nilai,
            'schedule' => $jadwal,
            'attendance' => [
                'source' => 'portal_wali_izin',
                'totalPengajuan' => (int) ($izinStats?->total_pengajuan ?? 0),
                'totalDisetujui' => (int) ($izinStats?->total_disetujui ?? 0),
                'totalPending' => (int) ($izinStats?->total_pending ?? 0),
            ],
        ];
    }

    public function getFinancialPayload(Santri $santri): array
    {
        $tagihan = Tagihan::query()
            ->where('santri_id', $santri->getKey())
            ->with('pembayaran')
            ->latest('jatuh_tempo')
            ->get()
            ->map(fn (Tagihan $item): array => [
                'id' => $item->id,
                'jenisTagihan' => $item->jenis_tagihan,
                'keterangan' => $item->keterangan,
                'nominalAkhir' => (float) $item->nominal_akhir,
                'totalDibayar' => (float) $item->total_dibayar,
                'sisaTagihan' => (float) $item->sisa_tagihan,
                'status' => $item->status,
                'jatuhTempo' => optional($item->jatuh_tempo)?->format('Y-m-d'),
                'periodeAkademik' => $item->periode_akademik,
            ])
            ->values();

        $pembayaran = Pembayaran::query()
            ->where('santri_id', $santri->getKey())
            ->with('tagihan')
            ->latest('tanggal_pembayaran')
            ->get()
            ->map(fn (Pembayaran $item): array => [
                'id' => $item->id,
                'tagihan' => $item->tagihan?->jenis_tagihan,
                'nominal' => (float) $item->nominal,
                'metode' => $item->metode,
                'tanggalPembayaran' => optional($item->tanggal_pembayaran)?->format('Y-m-d'),
                'isVerified' => (bool) $item->is_verified,
                'nomorBukti' => $item->nomor_bukti,
            ])
            ->values();

        $tabungan = Tabungan::query()
            ->where('santri_id', $santri->getKey())
            ->with(['transaksi' => fn ($query) => $query->latest('tanggal')->latest('id')])
            ->first();

        return [
            'bills' => $tagihan,
            'payments' => $pembayaran,
            'savings' => [
                'saldo' => (float) ($tabungan?->saldo ?? 0),
                'transactions' => collect($tabungan?->transaksi ?? [])
                    ->map(fn ($item): array => [
                        'id' => $item->id,
                        'tipe' => $item->tipe,
                        'nominal' => (float) $item->nominal,
                        'saldoSebelum' => (float) $item->saldo_sebelum,
                        'saldoSesudah' => (float) $item->saldo_sesudah,
                        'keterangan' => $item->keterangan,
                        'tanggal' => optional($item->tanggal)?->format('Y-m-d'),
                    ])
                    ->values(),
            ],
            'summary' => [
                'tagihanBelumLunas' => $tagihan->whereIn('status', ['belum_bayar', 'sebagian'])->count(),
                'totalOutstanding' => round($tagihan->sum('sisaTagihan'), 2),
            ],
        ];
    }

    public function getNotificationPayload(User $user): array
    {
        $targets = array_filter([
            $user->email,
            $user->name,
        ]);

        $notifications = LogNotifikasi::query()
            ->where(function (Builder $query) use ($targets): void {
                foreach ($targets as $target) {
                    $query->orWhere('target', $target);
                }
            })
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (LogNotifikasi $item): array => [
                'id' => $item->id,
                'jenis' => $item->jenis,
                'channel' => $item->channel,
                'target' => $item->target,
                'status' => $item->status,
                'errorMessage' => $item->error_message,
                'createdAt' => optional($item->created_at)?->format('Y-m-d H:i:s'),
                'payload' => $item->payload ?? [],
            ])
            ->values();

        return [
            'notifications' => $notifications,
            'summary' => [
                'sentCount' => $notifications->where('status', 'sent')->count(),
                'failedCount' => $notifications->where('status', 'failed')->count(),
            ],
        ];
    }

    public function getIzinPayload(User $user): array
    {
        return [
            'children' => $this->getAccessibleSantri($user),
            'submissions' => PortalWaliIzin::query()
                ->where('wali_id', $user->getKey())
                ->with('santri')
                ->latest('created_at')
                ->get()
                ->map(fn (PortalWaliIzin $item): array => [
                    'id' => $item->id,
                    'santri' => $item->santri?->nama,
                    'santriId' => $item->santri_id,
                    'jenisIzin' => $item->jenis_izin,
                    'tanggalMulai' => optional($item->tanggal_mulai)?->format('Y-m-d'),
                    'tanggalSelesai' => optional($item->tanggal_selesai)?->format('Y-m-d'),
                    'alasan' => $item->alasan,
                    'catatanWali' => $item->catatan_wali,
                    'status' => $item->status,
                    'catatanAdmin' => $item->catatan_admin,
                ])
                ->values(),
        ];
    }

    public function createIzin(User $user, array $validated): PortalWaliIzin
    {
        $santri = $this->findAccessibleSantri($user, $validated['santri_id']);

        return PortalWaliIzin::create([
            'tenant_id' => $santri->tenant_id,
            'santri_id' => $santri->id,
            'wali_id' => $user->getKey(),
            'jenis_izin' => $validated['jenis_izin'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'alasan' => $validated['alasan'],
            'catatan_wali' => $validated['catatan_wali'] ?? null,
            'status' => 'pending',
        ]);
    }

    // ── Tahfidz payload ──────────────────────────────────────────────────────

    public function getHafalanPayload(Santri $santri): array
    {
        $hafalan = HafalanTahfidz::query()
            ->where('santri_id', $santri->getKey())
            ->with(['penilaian' => fn ($q) => $q->orderByDesc('tanggal')->limit(1)])
            ->orderByDesc('tanggal_target')
            ->get()
            ->map(fn (HafalanTahfidz $h): array => [
                'id'              => $h->id,
                'surah'           => $h->surah,
                'ayat_awal'       => $h->ayat_awal,
                'ayat_akhir'      => $h->ayat_akhir,
                'status'          => $h->status,
                'tanggal_target'  => $h->tanggal_target?->toDateString(),
                'tanggal_selesai' => $h->tanggal_selesai?->toDateString(),
                'penilaian'       => $h->penilaian->map(fn ($p) => [
                    'jenis'  => $p->jenis,
                    'nilai'  => $p->nilai,
                    'tanggal'=> $p->tanggal?->toDateString(),
                ])->values(),
            ])
            ->values();

        $ringkasan = [
            'total'        => $hafalan->count(),
            'selesai'      => $hafalan->where('status', 'selesai')->count(),
            'berjalan'     => $hafalan->where('status', 'berjalan')->count(),
            'direncanakan' => $hafalan->where('status', 'direncanakan')->count(),
        ];

        return ['hafalan' => $hafalan, 'ringkasan' => $ringkasan];
    }

    // ── Kesehatan payload ─────────────────────────────────────────────────────

    public function getKesehatanPayload(Santri $santri): array
    {
        $riwayat = RiwayatKesehatan::query()
            ->where('santri_id', $santri->getKey())
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn (RiwayatKesehatan $r): array => [
                'id'       => $r->id,
                'tanggal'  => $r->tanggal?->toDateString(),
                'keluhan'  => $r->keluhan,
                'diagnosa' => $r->diagnosa,
                'tindakan' => $r->tindakan,
                'kondisi'  => $r->kondisi,
                'petugas'  => $r->petugas,
            ])
            ->values();

        $konseling = SesiKonseling::query()
            ->where('santri_id', $santri->getKey())
            ->with('konselor')
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn (SesiKonseling $s): array => [
                'id'          => $s->id,
                'tanggal'     => $s->tanggal?->toDateString(),
                'topik'       => $s->topik,
                'status'      => $s->status,
                'rekomendasi' => $s->rekomendasi,
                'konselor'    => $s->konselor?->name,
            ])
            ->values();

        return ['riwayat' => $riwayat, 'konseling' => $konseling];
    }

    // ── Kegiatan & Perizinan payload ──────────────────────────────────────────

    public function getKegiatanPayload(Santri $santri): array
    {
        $izin = IzinSantri::query()
            ->where('santri_id', $santri->getKey())
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->map(fn (IzinSantri $i): array => [
                'id'              => $i->id,
                'jenis_izin'      => $i->jenis_izin,
                'tanggal_mulai'   => $i->tanggal_mulai?->toDateString(),
                'tanggal_selesai' => $i->tanggal_selesai?->toDateString(),
                'alasan'          => $i->alasan,
                'status'          => $i->status,
                'sudah_kembali'   => $i->sudah_kembali,
            ])
            ->values();

        $kunjungan = KunjunganWali::query()
            ->where('santri_id', $santri->getKey())
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn (KunjunganWali $k): array => [
                'id'              => $k->id,
                'nama_pengunjung' => $k->nama_pengunjung,
                'hubungan'        => $k->hubungan,
                'tanggal'         => $k->tanggal?->toDateString(),
                'jam_datang'      => $k->jam_datang,
                'keperluan'       => $k->keperluan,
            ])
            ->values();

        return ['izin' => $izin, 'kunjungan' => $kunjungan];
    }

    protected function transformSantriSummary(User $user, Santri $santri): array
    {
        $kelas = $santri->kelas ?? $santri->penempatanAktif?->kelas;
        $accessRecord = $santri->accessWali->firstWhere('wali_id', $user->getKey());

        return [
            'id' => $santri->id,
            'nis' => $santri->nis,
            'nama' => $santri->nama,
            'status' => $santri->status,
            'kelas' => $kelas ? [
                'id' => $kelas->id,
                'nama' => $kelas->nama_kelas,
                'tingkat' => $kelas->tingkat,
            ] : null,
            'hubungan' => $accessRecord?->relationship_type ?? ($santri->wali_user_id === $user->getKey() ? 'wali' : 'wali'),
            'isPrimary' => (bool) ($accessRecord?->is_primary ?? ($santri->wali_user_id === $user->getKey())),
        ];
    }
}
