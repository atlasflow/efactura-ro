<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\Exceptions;

/** /upload answered ExecutionStatus 1. The messages are ANAF's `errorMessage` attributes. */
final class UploadRefused extends AnafException
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(public readonly array $messages, ?string $body = null)
    {
        parent::__construct('ANAF refused the upload: '.implode('; ', $messages), 200, $body);
    }
}
