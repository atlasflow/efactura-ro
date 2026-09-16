<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\Exceptions;

use RuntimeException;

/** The root of everything AnafClient throws. */
class AnafException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $body = null,
    ) {
        parent::__construct($message);
    }
}
