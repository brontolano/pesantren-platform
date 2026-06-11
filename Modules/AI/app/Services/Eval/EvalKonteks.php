<?php

namespace Modules\AI\Services\Eval;

use App\Models\User;

/**
 * T-A024 — konteks eksekusi satu run eval: tenant uji + aktor sintetis +
 * peta data setup per kasus. DTO immutable kecuali petaSantri (diisi
 * EvalTenantPreparer setelah setup data tool).
 */
final class EvalKonteks
{
    /**
     * @param  string|null  $tenantId  Tenant konteks eval (null = central, tidak lazim).
     * @param  bool  $tenantKhususEval  true = tenant sandbox milik harness: setting
     *                                  boleh diubah & data setup boleh dibuat. false
     *                                  (--tenant=...) = tenant nyata, HANYA dibaca —
     *                                  kasus ber-setup dilewati.
     * @param  User|null  $pemanggilAdmin  Aktor tool berperan admin_pesantren.
     * @param  User|null  $pemanggilWali  Aktor tool/kanal wa berperan wali.
     * @param  array<string, int>  $petaSantri  kode kasus → id santri hasil setup.
     */
    public function __construct(
        public readonly ?string $tenantId,
        public readonly bool $tenantKhususEval,
        public readonly ?User $pemanggilAdmin = null,
        public readonly ?User $pemanggilWali = null,
        public array $petaSantri = [],
    ) {}
}
