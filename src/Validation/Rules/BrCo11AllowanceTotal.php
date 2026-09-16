<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\AllowanceCharge;
use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/** BR-CO-11: Sum of allowances on document level (BT-107) = Σ Document level allowance amount (BT-92). */
final class BrCo11AllowanceTotal implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        $allowances = array_filter($document->allowancesCharges, fn (AllowanceCharge $ac) => ! $ac->isCharge);
        $sum = Amount::sum(array_map(fn (AllowanceCharge $ac) => $ac->amount, $allowances));
        $declared = $document->totals->allowanceTotal;

        if ($declared === null && $allowances === []) {
            return [];
        }

        if ($declared !== null && $this->sameCents($declared, $sum)) {
            return [];
        }

        return [$this->error('BR-CO-11', 'totals.allowanceTotal', sprintf('Document-level allowances sum to %s, BT-107 says %s.', $sum->rounded(2), $declared?->toString() ?? 'nothing'), ValidationSource::ARITHMETIC)];
    }
}
