<?php

namespace Modules\AI\Services\Gateway;

use RuntimeException;

/**
 * Kegagalan di sisi provider LLM (HTTP non-2xx, payload tak terduga, dst).
 * Ditangkap AiGateway untuk fallback — TIDAK pernah bocor ke pemanggil gateway.
 */
class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider = '',
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct($message);
    }
}
