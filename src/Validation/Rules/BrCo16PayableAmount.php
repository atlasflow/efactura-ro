<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/** BR-CO-16: Amount due for payment (BT-115) = BT-112 − Paid amount (BT-113) + Rounding amount (BT-114). */
final class BrCo16PayableAmount implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        $t = $document->totals;
        $expected = $t->taxInclusiveAmount
            ->minus($t->prepaidAmount ?? Amount::zero())
            ->plus($t->payableRoundingAmount ?? Amount::zero());

        if ($this->sameCents($t->payableAmount, $expected)) {
            return [];
        }

        return [$this->error('BR-CO-16', 'totals.payableAmount', sprintf('BT-112 − BT-113 + BT-114 is %s, BT-115 says %s.', $expected->rounded(2), $t->payableAmount), ValidationSource::ARITHMETIC)];
    }
}
