<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\AllowanceCharge;
use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/** BR-CO-12: Sum of charges on document level (BT-108) = Σ Document level charge amount (BT-99). */
final class BrCo12ChargeTotal implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        $charges = array_filter($document->allowancesCharges, fn (AllowanceCharge $ac) => $ac->isCharge);
        $sum = Amount::sum(array_map(fn (AllowanceCharge $ac) => $ac->amount, $charges));
        $declared = $document->totals->chargeTotal;

        if ($declared === null && $charges === []) {
            return [];
        }

        if ($declared !== null && $this->sameCents($declared, $sum)) {
            return [];
        }

        return [$this->error('BR-CO-12', 'totals.chargeTotal', sprintf('Document-level charges sum to %s, BT-108 says %s.', $sum->rounded(2), $declared?->toString() ?? 'nothing'), ValidationSource::ARITHMETIC)];
    }
}
