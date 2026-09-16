<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/**
 * BR-CO-25: when the Amount due for payment (BT-115) is positive, the
 * Payment due date (BT-9) or the Payment terms (BT-20) must be present.
 * The schematron applies it to invoices only; credit notes are exempt.
 */
final class BrCo25PaymentDueDate implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        if ($document->isCreditNote() || ! $document->totals->payableAmount->isPositive()) {
            return [];
        }

        if ($document->dueDate !== null || $document->paymentTerms !== null) {
            return [];
        }

        return [$this->error('BR-CO-25', 'dueDate', 'A positive amount due needs a due date or payment terms.', ValidationSource::EN16931)];
    }
}
