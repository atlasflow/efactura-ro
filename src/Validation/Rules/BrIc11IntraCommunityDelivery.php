<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\TaxSubtotal;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;
use AtlasFlow\EFacturaRo\Vat\VatCategory;

/**
 * BR-IC-11: an intra-community supply (category K) needs the Actual
 * delivery date (BT-72) or an Invoicing period (BG-14).
 * BR-IC-12: it also needs the Deliver-to country code (BT-80).
 */
final class BrIc11IntraCommunityDelivery implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        if (! array_any($document->taxSubtotals, fn (TaxSubtotal $s) => $s->category === VatCategory::K)) {
            return [];
        }

        $errors = [];

        if ($document->delivery?->actualDate === null && $document->period === null) {
            $errors[] = $this->error('BR-IC-11', 'delivery.actualDate', 'An intra-community supply needs an actual delivery date or an invoicing period.', ValidationSource::EN16931);
        }

        if ($document->delivery?->address === null) {
            $errors[] = $this->error('BR-IC-12', 'delivery.address', 'An intra-community supply needs a deliver-to address with its country code.', ValidationSource::EN16931);
        }

        return $errors;
    }
}
