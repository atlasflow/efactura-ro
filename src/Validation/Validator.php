<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation;

use AtlasFlow\EFacturaRo\Document\Document;

/**
 * Local then remote, stopping at the first failing layer: there is no point
 * asking ANAF about a document whose totals do not add up, and no point
 * trusting a local pass without ANAF's word.
 */
final class Validator
{
    public function __construct(
        private readonly LocalValidator $local,
        private readonly ?RemoteValidator $remote = null,
    ) {}

    public function local(Document $document): ValidationResult
    {
        return $this->local->validate($document);
    }

    public function full(Document $document): ValidationResult
    {
        $local = $this->local->validate($document);

        if (! $local->ok || $this->remote === null) {
            return $local;
        }

        return $this->remote->validate($document);
    }
}
