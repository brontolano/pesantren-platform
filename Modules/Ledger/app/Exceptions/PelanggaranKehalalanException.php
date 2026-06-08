<?php

namespace Modules\Ledger\Exceptions;

use RuntimeException;

/**
 * Dilempar saat posting melanggar aturan pemisahan dana halal/syubhat
 * (mis. dana syubhat dipakai untuk sarana ibadah, atau dicatat sebagai pendapatan).
 */
class PelanggaranKehalalanException extends RuntimeException
{
}
