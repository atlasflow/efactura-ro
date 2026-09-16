<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\TaxSubtotal;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/** BR-CO-14: Invoice total VAT amount (BT-110) = Σ VAT category tax amount (BT-117). */
final class BrCo14TaxTotal implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        $sum = Amount::sum(array_map(fn (TaxSubtotal $s) => $s->taxAmount, $document->taxSubtotals));

        if ($this->sameCents($document->totals->taxAmount, $sum)) {
            return [];
        }

        return [$this->error('BR-CO-14', 'totals.taxAmount', sprintf('VAT breakdown tax amounts sum to %s, BT-110 says %s.', $sum->rounded(2), $document->totals->taxAmount), ValidationSource::ARITHMETIC)];
    }
}
