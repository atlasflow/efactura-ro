<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/** BR-CO-15: Invoice total amount with VAT (BT-112) = BT-109 + BT-110. */
final class BrCo15TaxInclusiveAmount implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        $t = $document->totals;
        $expected = $t->taxExclusiveAmount->plus($t->taxAmount);

        if ($this->sameCents($t->taxInclusiveAmount, $expected)) {
            return [];
        }

        return [$this->error('BR-CO-15', 'totals.taxInclusiveAmount', sprintf('BT-109 + BT-110 is %s, BT-112 says %s.', $expected->rounded(2), $t->taxInclusiveAmount), ValidationSource::ARITHMETIC)];
    }
}
