<?php

namespace Modules\Kesantrian\Services;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Tahfidz\Models\Halaqah;

class PresensiAsramaService
{
    public const SETTING_SLOT_PRESENSI = 'kepengasuhan.slot_presensi';

    public const SETTING_DEFAULT_HADIR = 'kepengasuhan.presensi_default_hadir';

    public const DEFAULT_SLOTS = [
        ['kode' => 'subuh', 'label' => 'Sholat Subuh', 'jam_buka' => '04:00', 'jam_tutup' => '06:00'],
        ['kode' => 'maghrib', 'label' => 'Sholat Maghrib', 'jam_buka' => '17:30', 'jam_tutup' => '19:30'],
        ['kode' => 'isya', 'label' => 'Sholat Isya', 'jam_buka' => '19:00', 'jam_tutup' => '21:00'],
    ];

    public const VALID_STATUSES = ['hadir', 'alpha', 'izin', 'sakit', 'terlambat'];

    public function slotAktif(): array
    {
        $raw = Setting::getTenantValue(self::SETTING_SLOT_PRESENSI, self::DEFAULT_SLOTS);

        if (! is_array($raw)) {
            return self::DEFAULT_SLOTS;
        }

        return array_values(array_filter($raw, fn ($s) => is_array($s) && ! empty($s['kode']) && ! empty($s['label'])));
    }

    public function kodeSlotAktif(): array
    {
        return array_column($this->slotAktif(), 'kode');
    }

    public function defaultHadir(): bool
    {
        $val = Setting::getTenantValue(self::SETTING_DEFAULT_HADIR, true);

        if (is_bool($val)) {
            return $val;
        }

        if (is_string($val)) {
            return ! in_array(strtolower($val), ['false', '0', 'no', ''], true);
        }

        return (bool) $val;
    }

    public function simpanPresensi(
        ?KamarAsrama $kamar,
        ?Halaqah $halaqah,
        Carbon $tanggal,
        string $slot,
        array $dataSantri,
        User $pencatat,
    ): int {
        $this->validateSlot($slot);
        $this->validateTanggal($tanggal);

        $count = 0;

        foreach ($dataSantri as $entry) {
            $santriId = $entry['santri_id'];
            $status = $entry['status'] ?? 'hadir';

            if (! in_array($status, self::VALID_STATUSES, true)) {
                throw new InvalidArgumentException("Status '{$status}' tidak valid.");
            }

            PresensiAsrama::updateOrCreate(
                [
                    'tenant_id' => tenant('id'),
                    'santri_id' => $santriId,
                    'tanggal' => $tanggal->toDateString(),
                    'slot' => $slot,
                ],
                [
                    'status' => $status,
                    'keterangan' => $entry['keterangan'] ?? null,
                    'pencatat_id' => $pencatat->id,
                    'kamar_id' => $kamar?->id,
                    'halaqah_id' => $halaqah?->id,
                ],
            );
            $count++;
        }

        return $count;
    }

    public function rekapKamar(int $kamarId, Carbon $tanggal, ?string $slot = null): Collection
    {
        $query = PresensiAsrama::query()
            ->where('kamar_id', $kamarId)
            ->where('tanggal', $tanggal->toDateString());

        if ($slot !== null) {
            $query->where('slot', $slot);
        }

        return $query->get()
            ->groupBy('slot')
            ->map(fn (Collection $rows) => $rows->groupBy('status')->map->count());
    }

    public function rekapSantri(int $santriId, Carbon $dari, Carbon $sampai): Collection
    {
        return PresensiAsrama::query()
            ->where('santri_id', $santriId)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->orderBy('tanggal')
            ->orderBy('slot')
            ->get();
    }

    private function validateSlot(string $slot): void
    {
        $aktif = $this->kodeSlotAktif();

        if (! in_array($slot, $aktif, true)) {
            throw new InvalidArgumentException("Slot '{$slot}' tidak aktif di pesantren ini.");
        }
    }

    private function validateTanggal(Carbon $tanggal): void
    {
        $today = Carbon::today();
        $minDate = $today->copy()->subDays(7);

        if ($tanggal->greaterThan($today)) {
            throw new InvalidArgumentException('Tanggal tidak boleh lebih dari hari ini.');
        }

        if ($tanggal->lessThan($minDate)) {
            throw new InvalidArgumentException('Tanggal tidak boleh lebih dari 7 hari yang lalu.');
        }
    }
}
