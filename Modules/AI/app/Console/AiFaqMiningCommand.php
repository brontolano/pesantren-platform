<?php

namespace Modules\AI\Console;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\AI\Services\Research\FaqMiner;
use Modules\Notifikasi\Models\LogNotifikasi;
use Throwable;

/**
 * FAQ-Mining — ai:faq-mining: tambang pertanyaan berulang → usulan FAQ
 * per tenant (dijadwalkan MINGGUAN, pola loop AiEskalasiPengingatCommand).
 *
 * Per tenant ber-modul AI aktif (gating ModuleService, sama dengan halaman
 * panel): FaqMiner::tambang() → bila ada usulan BARU, beri tahu seluruh
 * pemegang `ai.pengetahuan.kelola` via LogNotifikasi database (pola
 * WaHandoffService; fallback satu baris 'admin_tenant' bila belum ada
 * pemegang izin). HUMAN-IN-THE-LOOP: perintah ini hanya MENGUSULKAN —
 * tidak ada apa pun yang masuk RAG tanpa pengesahan manusia.
 *
 * Selalu exit 0 (pola penjadwal platform): kegagalan satu tenant di-log
 * dan tidak menghentikan tenant lain.
 */
class AiFaqMiningCommand extends Command
{
    protected $signature = 'ai:faq-mining
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}
        {--hari= : Jendela penambangan (hari ke belakang); kosong = config}';

    protected $description = 'Tambang pertanyaan berulang (eskalasi + percakapan) menjadi usulan FAQ per tenant + notifikasi pengelola pengetahuan';

    public function handle(FaqMiner $miner, ModuleService $modules): int
    {
        $target = $this->argument('tenant');
        $hari = $this->option('hari') !== null ? (int) $this->option('hari') : null;

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->error($target ? "Tenant '{$target}' tidak ditemukan." : 'Tidak ada tenant.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($tenants as $tenant) {
            // Gating modul: hanya tenant ber-modul AI aktif/tampil yang
            // ditambang (cek di konteks CENTRAL — registry & tenant_modules).
            if (! $modules->isVisibleForTenant('ai', $tenant)) {
                $rows[] = [$tenant->getKey(), '-', '-', 'modul AI nonaktif'];

                continue;
            }

            try {
                $stat = $tenant->run(function () use ($miner, $hari): ?array {
                    // Tenant lama belum termigrasi — lewati anggun.
                    if (! Schema::hasTable('ai_faq_draf')) {
                        return null;
                    }

                    $stat = $miner->tambang($hari);

                    if ($stat['usulan_baru'] > 0) {
                        $this->notifikasiKePengelola($stat['usulan_baru']);
                    }

                    return $stat;
                });

                $rows[] = $stat === null
                    ? [$tenant->getKey(), '-', '-', 'dilewati (tabel belum ada)']
                    : [$tenant->getKey(), $stat['usulan_baru'], $stat['diperbarui'], 'OK'];
            } catch (Throwable $e) {
                Log::error('ai:faq-mining gagal untuk tenant.', [
                    'tenant' => $tenant->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $rows[] = [$tenant->getKey(), '-', '-', 'GAGAL'];
            }
        }

        $this->table(['Tenant', 'Usulan baru', 'Diperbarui', 'Status'], $rows);
        $this->info('Penambangan usulan FAQ selesai.');

        return self::SUCCESS;
    }

    /**
     * Notifikasi database ke seluruh pemegang `ai.pengetahuan.kelola`
     * (langsung atau via role — scope permission() spatie, pola
     * WaHandoffService). Tanpa pemegang = satu baris 'admin_tenant' supaya
     * usulan tidak hilang senyap. Best-effort: kegagalan menulis notifikasi
     * tidak menggagalkan penambangan.
     */
    private function notifikasiKePengelola(int $usulanBaru): void
    {
        try {
            $targets = User::permission('ai.pengetahuan.kelola')
                ->pluck('id')
                ->map(fn ($id): string => 'user:'.$id)
                ->all();
        } catch (Throwable) {
            $targets = [];
        }

        if ($targets === []) {
            $targets = ['admin_tenant'];
        }

        foreach ($targets as $targetNotif) {
            try {
                LogNotifikasi::query()->create([
                    'jenis' => 'ai_faq_usulan',
                    'channel' => 'database',
                    'target' => $targetNotif,
                    'status' => 'sent',
                    'payload' => [
                        'judul' => 'Usulan FAQ baru dari pertanyaan pengguna',
                        'pesan' => sprintf(
                            'Asisten menemukan %d usulan FAQ baru dari pertanyaan yang sering '
                            .'diajukan pengguna. Mohon berkenan meninjau, mengisi jawaban, dan '
                            .'mengesahkannya pada halaman Usulan FAQ agar asisten dapat '
                            .'menjawabnya secara otomatis. Jazakumullahu khairan.',
                            $usulanBaru,
                        ),
                        'usulan_baru' => $usulanBaru,
                    ],
                    'created_at' => now(),
                ]);
            } catch (Throwable $e) {
                Log::error('ai:faq-mining: GAGAL menulis notifikasi usulan FAQ.', [
                    'target' => $targetNotif,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
