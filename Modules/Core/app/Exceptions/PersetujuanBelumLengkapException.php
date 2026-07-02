<?php

namespace Modules\Core\Exceptions;

use RuntimeException;

/**
 * Dilempar saat transaksi wajib-approval dilanjutkan sebelum disetujui.
 */
class PersetujuanBelumLengkapException extends RuntimeException {}
