<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/**
 * BR-CO-17: VAT category tax amount (BT-117) = VAT category taxable amount
 * (BT-116) × (VAT category rate (BT-119) ÷ 100), rounded to two decimals.
 *
 * Checked as MF's schematron checks it: a zero rate needs a zero tax amount,
 * and otherwise |BT-117| must lie strictly within one unit of the rounded
 * product — the standard tolerates the cent drift that per-line rounding
 * produces. BR-S-09 is the same test restricted to category S, so a
 * failure here is reported under both ids when the category is S.
 */
final class BrCo17SubtotalTaxAmount implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        $errors = [];

        foreach ($document->taxSubtotals as $index => $subtotal) {
            $path = sprintf('taxSubtotals[%d].taxAmount', $index);
            $rate = $subtotal->rate;

            if ($rate === null || $rate->rounded(0)->isZero()) {
                if (! $subtotal->taxAmount->rounded(0)->isZero()) {
                    $errors[] = $this->error('BR-CO-17', $path, sprintf('A zero-rate breakdown must have a zero tax amount, found %s.', $subtotal->taxAmount), ValidationSource::ARITHMETIC);
                }

                continue;
            }

            $expected = $subtotal->taxableAmount->abs()->multipliedBy($rate)->dividedBy('100', 6)->rounded(2);

            if (! $this->withinOneUnit($subtotal->taxAmount, $expected)) {
                $message = sprintf('BT-116 %s × %s %% is %s, BT-117 says %s.', $subtotal->taxableAmount, $rate, $expected, $subtotal->taxAmount);
                $errors[] = $this->error('BR-CO-17', $path, $message, ValidationSource::ARITHMETIC);

                if ($subtotal->category->value === 'S') {
                    $errors[] = $this->error('BR-S-09', $path, $message, ValidationSource::ARITHMETIC);
                }
            }
        }

        return $errors;
    }
}
