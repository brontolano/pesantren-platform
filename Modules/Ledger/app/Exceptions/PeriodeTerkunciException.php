<?php

namespace Modules\Ledger\Exceptions;

use RuntimeException;

/**
 * Dilempar saat ada upaya posting bertanggal periode berstatus `closed` (Pagar #4).
 */
class PeriodeTerkunciException extends RuntimeException {}
