<?php

namespace Modules\AI\Services\Rag;

/**
 * T-A031 — namespace sumber retrieval (Blueprint §5: aturan 2-koneksi).
 *
 * TENANT = korpus DB tenant aktif (ai_knowledge_source/ai_chunk) — v1.
 * SHARED = korpus kitab di DB central — STUB: implementasi menunggu T-A032
 * (gerbang Q3: lisensi terbuka korpus). Antarmuka sudah siap-2-sumber
 * supaya pemanggil tidak berubah saat shared menyala.
 */
enum RetrieverSource: string
{
    case TENANT = 'tenant';

    case SHARED = 'shared';
}
