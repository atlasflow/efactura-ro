<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/** BR-RO-001: the Specification identifier (BT-24) is exactly the CIUS-RO 1.0.1 id; the writer always emits it, a read document may not carry it. */
final class BrRo001CustomizationId implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        if ($document->customizationId === Document::CIUS_RO_CUSTOMIZATION_ID) {
            return [];
        }

        return [$this->error('BR-RO-001', 'customizationId', sprintf('Expected "%s", found "%s".', Document::CIUS_RO_CUSTOMIZATION_ID, $document->customizationId), ValidationSource::CIUS_RO)];
    }
}
