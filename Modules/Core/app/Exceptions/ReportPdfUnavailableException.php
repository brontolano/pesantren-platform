<?php

namespace Modules\Core\Exceptions;

use RuntimeException;

/**
 * F8 — dilempar saat ekspor PDF diminta tetapi library PDF (dompdf) belum
 * terpasang. Aktifkan dengan: composer require barryvdh/laravel-dompdf.
 */
class ReportPdfUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Library PDF belum terpasang. Jalankan: composer require barryvdh/laravel-dompdf')
    {
        parent::__construct($message);
    }
}
