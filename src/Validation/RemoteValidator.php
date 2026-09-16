<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation;

use AtlasFlow\EFacturaRo\Anaf\AnafClient;
use AtlasFlow\EFacturaRo\Document\Document;

/** ANAF's public validare endpoint — the authoritative check, no credentials needed. */
final class RemoteValidator
{
    public function __construct(private readonly AnafClient $client) {}

    public function validate(Document $document): ValidationResult
    {
        return $this->client->validateDocument($document);
    }

    public function validateXml(string $xml): ValidationResult
    {
        return $this->client->validate($xml);
    }
}
