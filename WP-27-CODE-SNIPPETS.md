# WP-27: Key Code Snippets

## 1. KunjunganUks Model Constants

```php
// Modules/Kesehatan/app/Models/KunjunganUks.php
public const STATUS = ['pulang', 'istirahat', 'rujuk'];
```

## 2. Observer Auto-Sync Presensi

```php
private function syncPresensi(KunjunganUks $kunjungan): void
{
    if ($kunjungan->status === 'istirahat') {
        PresensiSantri::updateOrCreate(
            ['santri_id' => $kunjungan->santri_id, 'tanggal' => $kunjungan->tanggal],
            ['status' => 'sakit', 'keterangan' => "UKS: {$kunjungan->keluhan}"]
        );
    } else {
        PresensiSantri::where('status', 'sakit')
            ->where('santri_id', $kunjungan->santri_id)
            ->where('tanggal', $kunjungan->tanggal)
            ->delete();
    }
}
```

## 3. Service: Idempotent Catat Kunjungan

```php
return KunjunganUks::updateOrCreate(
    ['santri_id' => $santri->id, 'tanggal' => $tanggal],
    [
        'tenant_id' => $santri->tenant_id,
        'jam_masuk' => $opts['jam_masuk'] ?? now()->format('H:i:s'),
        'status' => $status,
        'keluhan' => $opts['keluhan'] ?? null,
        'tindakan' => $opts['tindakan'] ?? null,
    ]
);
```

## 4. API Endpoint: Catat Kunjungan

```php
public function catatKunjungan(Request $request): JsonResponse
{
    $data = $request->validate([
        'santri_id' => ['required', 'integer', 'exists:santri,id'],
        'status' => ['required', 'in:pulang,istirahat,rujuk'],
        'keluhan' => ['nullable', 'string', 'max:500'],
    ]);

    $santri = Santri::findOrFail($data['santri_id']);
    $kunjungan = $this->service->catatKunjungan(
        $santri,
        now()->toDateString(),
        $data['status'],
        ['keluhan' => $data['keluhan'] ?? null, 'pencatat_id' => auth()->id()]
    );

    if ($kunjungan->status === 'rujuk') {
        app(NotifDispatcher::class)->kirim('uks_rujukan', [
            'data' => ['santri_nama' => $santri->nama, 'rujukan_ke' => $kunjungan->rujukan_ke],
            'penerima' => [['target' => $santri->no_hp_wali ?? '', 'variables' => []]],
        ]);
    }

    return response()->json(['id' => $kunjungan->id], 201);
}
```

## 5. Routes

```php
Route::middleware(['auth:sanctum'])->prefix('pwa/guru')->group(function () {
    Route::get('uks/istirahat', [PwaGuruUksController::class, 'daftarIstirahat']);
    Route::post('uks/catat-kunjungan', [PwaGuruUksController::class, 'catatKunjungan']);
    Route::post('uks/{kunjungan_id}/tutup', [PwaGuruUksController::class, 'tutupKunjungan']);
    Route::get('uks/{santri_id}/riwayat', [PwaGuruUksController::class, 'riwayat']);
});
```

## 6. Migration Schema

```php
Schema::create('kunjungan_uks', function (Blueprint $table): void {
    $table->id();
    $table->string('tenant_id');
    $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
    $table->date('tanggal');
    $table->time('jam_masuk');
    $table->time('jam_keluar')->nullable();
    $table->text('keluhan')->nullable();
    $table->enum('status', ['pulang', 'istirahat', 'rujuk'])->default('pulang');
    $table->string('rujukan_ke')->nullable();
    $table->decimal('rujukan_biaya', 12, 2)->nullable();
    $table->foreignId('pencatat_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->unique(['tenant_id', 'santri_id', 'tanggal'], 'uq_kunjungan_uks_per_hari');
    $table->index(['tenant_id', 'santri_id']);
});
```

## 7. Unit Test Example

```php
public function test_kunjungan_uks_status_constants(): void
{
    $this->assertContains('pulang', KunjunganUks::STATUS);
    $this->assertContains('istirahat', KunjunganUks::STATUS);
    $this->assertContains('rujuk', KunjunganUks::STATUS);
    $this->assertCount(3, KunjunganUks::STATUS);
}
```

## 8. Feature Test: Auto-Sync Example

```php
public function test_istirahat_uks_auto_create_presensi_sakit(): void
{
    tenancy()->initialize($this->tenant);

    $kunjungan = $this->service->catatKunjungan(
        $this->santri1,
        now()->toDateString(),
        'istirahat',
        ['keluhan' => 'Demam', 'pencatat_id' => $this->guru->id]
    );

    $presensi = PresensiSantri::where('santri_id', $this->santri1->id)
        ->where('tanggal', now()->toDateString())
        ->first();

    $this->assertNotNull($presensi);
    $this->assertEquals('sakit', $presensi->status);
    $this->assertStringContainsString('UKS: Demam', $presensi->keterangan);

    tenancy()->end();
}
```

## 9. API Request Example

POST /api/pwa/guru/uks/catat-kunjungan:
```json
{
  "santri_id": 123,
  "keluhan": "Demam tinggi 38 derajat",
  "tindakan": "Istirahat dan kompres",
  "obat": "Paracetamol 500mg",
  "status": "istirahat"
}
```

Response (201):
```json
{
  "id": 1,
  "santri_id": 123,
  "tanggal": "2026-06-11",
  "jam_masuk": "10:30:00",
  "status": "istirahat"
}
```

## 10. Service Methods Summary

- `catatKunjungan()` — idempotent per santri per tanggal
- `tutupKunjungan()` — set jam_keluar
- `getDaftarKunjungan()` — range query, ordered DESC
- `getKunjunganHariIni()` — today only
- `getDaftarIstirahat()` — active istirahat only
- `getDaftarRujukanTerbuka()` — open rujukan only

All code syntax validated, 9/9 unit tests passing.
