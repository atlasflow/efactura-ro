<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/**
 * EN 16931-1 §6.5 line calculation, not a schematron assertion: the Invoice
 * line net amount (BT-131) = Invoiced quantity (BT-129) × (Item net price
 * (BT-146) ÷ Item price base quantity (BT-149)) − Σ line allowances (BT-136)
 * + Σ line charges (BT-141), rounded to two decimals. ANAF does not check
 * this; a caller who supplies inconsistent lines would otherwise only find
 * out through BR-CO-10 at document level. One cent of slack absorbs the
 * difference between half-up and half-even rounding upstream.
 */
final class LineNetAmount implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        $errors = [];

        foreach ($document->lines as $index => $line) {
            $expected = $line->grossAmount()->plus($line->allowanceChargeTotal())->rounded(2);
            $difference = $line->netAmount->minus($expected)->abs();

            if ($difference->isGreaterThan(Amount::of('0.01'))) {
                $errors[] = $this->error(
                    'LINE-NET-AMOUNT',
                    sprintf('lines[%d].netAmount', $index),
                    sprintf('Line %s: net amount %s does not match quantity × price ± line allowances and charges (%s).', $line->id, $line->netAmount, $expected),
                    ValidationSource::ARITHMETIC,
                );
            }
        }

        return $errors;
    }
}
