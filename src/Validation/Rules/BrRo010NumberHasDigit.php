<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/** BR-RO-010: the Invoice number (BT-1) includes at least one digit. BR-RO-L155: at most 200 characters. */
final class BrRo010NumberHasDigit implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        $errors = [];

        if (! preg_match('/\d/', $document->number)) {
            $errors[] = $this->error('BR-RO-010', 'number', 'The document number must contain at least one digit.', ValidationSource::CIUS_RO);
        }

        if (mb_strlen(trim($document->number)) > 200) {
            $errors[] = $this->error('BR-RO-L155', 'number', 'The document number is longer than 200 characters.', ValidationSource::CIUS_RO);
        }

        return $errors;
    }
}
