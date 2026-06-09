<?php

namespace Modules\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\ApprovalRequest;

/**
 * Dipancarkan saat permintaan persetujuan diputuskan (disetujui/ditolak).
 * Modul consumer mendengar ini untuk melanjutkan/membatalkan posting.
 */
class ApprovalDiputuskan
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ApprovalRequest $request,
        public bool $disetujui,
    ) {
    }
}
