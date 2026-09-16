<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\Line;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/** BR-CO-10: Sum of Invoice line net amount (BT-106) = Σ Invoice line net amount (BT-131). */
final class BrCo10LineExtensionAmount implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        $sum = Amount::sum(array_map(fn (Line $l) => $l->netAmount, $document->lines));

        if ($this->sameCents($document->totals->lineExtensionAmount, $sum)) {
            return [];
        }

        return [$this->error('BR-CO-10', 'totals.lineExtensionAmount', sprintf('Sum of line net amounts is %s, BT-106 says %s.', $sum->rounded(2), $document->totals->lineExtensionAmount), ValidationSource::ARITHMETIC)];
    }
}
