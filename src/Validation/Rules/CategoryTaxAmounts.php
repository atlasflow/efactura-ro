<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/**
 * BR-Z-09, BR-E-09, BR-AE-09, BR-IC-09, BR-G-09, BR-O-09: the tax amount
 * (BT-117) of a breakdown in any category but Standard rated is zero.
 * (BR-S-09, the standard-rated product, is reported by BrCo17SubtotalTaxAmount.)
 */
final class CategoryTaxAmounts implements Rule
{
    use Concerns;

    private const array RULE_IDS = [
        'Z' => 'BR-Z-09', 'E' => 'BR-E-09', 'AE' => 'BR-AE-09', 'K' => 'BR-IC-09', 'G' => 'BR-G-09', 'O' => 'BR-O-09',
    ];

    public function check(Document $document): array
    {
        $errors = [];

        foreach ($document->taxSubtotals as $index => $subtotal) {
            if (! $subtotal->category->taxAmountMustBeZero() || $subtotal->taxAmount->isZero()) {
                continue;
            }

            $errors[] = $this->error(
                self::RULE_IDS[$subtotal->category->value],
                sprintf('taxSubtotals[%d].taxAmount', $index),
                sprintf('A breakdown in category %s must have a zero tax amount, found %s.', $subtotal->category->value, $subtotal->taxAmount),
                ValidationSource::ARITHMETIC,
            );
        }

        return $errors;
    }
}
