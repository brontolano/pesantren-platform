<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * T-A004 — satu versi system prompt sebuah agen (tabel CENTRAL).
 *
 * ADR-4: prompt = artefak ber-versi. ISI (system_prompt) SEKALI TULIS —
 * perubahan isi = versi baru (baris baru), JANGAN edit versi lama. Ditegakkan
 * oleh guard saving di bawah (bukan trigger DB, karena STATUS memang boleh
 * berubah: draft → aktif → arsip saat aktivasi/rollback).
 */
class AiPromptVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_ARSIP = 'arsip';

    protected $table = 'ai_prompt_version';

    protected $fillable = [
        'agent_key',
        'versi',
        'system_prompt',
        'changelog',
        'status',
        'created_by',
    ];

    protected $casts = [
        'versi' => 'integer',
        'created_by' => 'integer',
    ];

    protected static function booted(): void
    {
        // Guard sekali-tulis: system_prompt tidak boleh diubah setelah tersimpan.
        static::updating(function (self $version): void {
            if ($version->isDirty('system_prompt')) {
                throw new LogicException(
                    'system_prompt versi lama tidak boleh diedit (artefak ber-versi, ADR-4) — '
                    .'buat versi baru lalu aktifkan.',
                );
            }
        });
    }

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
