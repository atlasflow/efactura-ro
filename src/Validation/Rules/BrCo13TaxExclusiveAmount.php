<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/** BR-CO-13: Invoice total amount without VAT (BT-109) = BT-106 − BT-107 + BT-108. */
final class BrCo13TaxExclusiveAmount implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        $t = $document->totals;
        $expected = $t->lineExtensionAmount
            ->minus($t->allowanceTotal ?? Amount::zero())
            ->plus($t->chargeTotal ?? Amount::zero());

        if ($this->sameCents($t->taxExclusiveAmount, $expected)) {
            return [];
        }

        return [$this->error('BR-CO-13', 'totals.taxExclusiveAmount', sprintf('BT-106 − BT-107 + BT-108 is %s, BT-109 says %s.', $expected->rounded(2), $t->taxExclusiveAmount), ValidationSource::ARITHMETIC)];
    }
}
