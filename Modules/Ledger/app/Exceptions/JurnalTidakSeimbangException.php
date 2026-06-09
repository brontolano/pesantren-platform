<?php

namespace Modules\Ledger\Exceptions;

use RuntimeException;

/**
 * Dilempar saat total debit != total kredit (Pagar #1: Σdebit = Σkredit).
 */
class JurnalTidakSeimbangException extends RuntimeException
{
}
