<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\Exceptions;

/**
 * HTTP 429, or one of the quota messages ANAF sends inside a 200
 * ("S-au facut deja 10 descarcari de mesaj in cursul zilei"). `retryAfter`
 * is the header value in seconds when ANAF sent one; `quota` names the
 * limit the caller most likely hit.
 */
final class RateLimited extends AnafException
{
    public function __construct(string $message, ?int $httpStatus = null, ?string $body = null, public readonly ?int $retryAfter = null, public readonly ?string $quota = null)
    {
        parent::__construct($message, $httpStatus, $body);
    }
}
