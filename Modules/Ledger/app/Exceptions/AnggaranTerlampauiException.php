<?php

namespace Modules\Ledger\Exceptions;

use RuntimeException;

/**
 * Dilempar saat transaksi belanja melampaui pagu RKAM yang mode-nya 'blokir'.
 */
class AnggaranTerlampauiException extends RuntimeException {}
