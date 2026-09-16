<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\AllowanceCharge;
use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\Line;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;
use AtlasFlow\EFacturaRo\Vat\VatCategory;

/**
 * BR-S-08, BR-Z-08, BR-E-08, BR-AE-08, BR-IC-08, BR-G-08, BR-O-08: the
 * taxable amount (BT-116) of each VAT breakdown equals the sum of line net
 * amounts plus document-level charges minus document-level allowances in
 * that category — and, for Standard rated, at that rate.
 *
 * The schematron tolerates less than one unit of difference for S (BR-S-08)
 * and demands equality for the other categories; this rule does the same.
 */
final class CategoryTaxableAmounts implements Rule
{
    use Concerns;

    private const array RULE_IDS = [
        'S' => 'BR-S-08', 'Z' => 'BR-Z-08', 'E' => 'BR-E-08', 'AE' => 'BR-AE-08',
        'K' => 'BR-IC-08', 'G' => 'BR-G-08', 'O' => 'BR-O-08', 'L' => 'BR-IG-08', 'M' => 'BR-IP-08',
    ];

    public function check(Document $document): array
    {
        $errors = [];

        foreach ($document->taxSubtotals as $index => $subtotal) {
            $expected = $this->expectedTaxable($document, $subtotal->category, $subtotal->category === VatCategory::S ? $subtotal->rate : null);
            $matches = $subtotal->category === VatCategory::S
                ? $this->withinOneUnit($subtotal->taxableAmount, $expected)
                : $this->sameCents($subtotal->taxableAmount, $expected);

            if (! $matches) {
                $errors[] = $this->error(
                    self::RULE_IDS[$subtotal->category->value],
                    sprintf('taxSubtotals[%d].taxableAmount', $index),
                    sprintf('Lines and document-level allowances/charges in category %s%s sum to %s, BT-116 says %s.', $subtotal->category->value, $subtotal->rate === null ? '' : ' at '.$subtotal->rate.' %', $expected->rounded(2), $subtotal->taxableAmount),
                    ValidationSource::ARITHMETIC,
                );
            }
        }

        return $errors;
    }

    private function expectedTaxable(Document $document, VatCategory $category, ?Amount $rate): Amount
    {
        $same = fn (VatCategory $c, ?Amount $r): bool => $c === $category && ($rate === null || ($r !== null && $r->rounded(2)->equals($rate->rounded(2))));

        $lines = array_filter($document->lines, fn (Line $l) => $same($l->vat->category, $l->vat->rate));
        $entries = array_filter($document->allowancesCharges, fn (AllowanceCharge $ac) => $ac->vat !== null && $same($ac->vat->category, $ac->vat->rate));

        return Amount::sum(array_map(fn (Line $l) => $l->netAmount, $lines))
            ->plus(Amount::sum(array_map(fn (AllowanceCharge $ac) => $ac->signedAmount(), $entries)));
    }
}
