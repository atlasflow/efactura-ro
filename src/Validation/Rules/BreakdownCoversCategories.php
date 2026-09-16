<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\TaxSubtotal;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;
use AtlasFlow\EFacturaRo\Vat\VatCategory;

/**
 * BR-S-01, BR-Z-01, BR-E-01, BR-AE-01, BR-IC-01, BR-G-01, BR-O-01: every
 * category used on a line or a document-level allowance/charge appears in
 * the VAT breakdown, and every breakdown category is used somewhere.
 * For Standard rated the check is per rate, as BR-S-08 phrases it: a line
 * at 11 % with no 11 % breakdown is reported under BR-S-08.
 * BR-O-11..14: a "Not subject to VAT" breakdown stands alone.
 */
final class BreakdownCoversCategories implements Rule
{
    use Concerns;

    private const array RULE_IDS = [
        'S' => 'BR-S-01', 'Z' => 'BR-Z-01', 'E' => 'BR-E-01', 'AE' => 'BR-AE-01', 'K' => 'BR-IC-01', 'G' => 'BR-G-01', 'O' => 'BR-O-01', 'L' => 'BR-IG-01', 'M' => 'BR-IP-01',
    ];

    public function check(Document $document): array
    {
        $used = [];

        foreach ($document->lines as $line) {
            $used[$line->vat->category->value] = true;
        }

        foreach ($document->allowancesCharges as $entry) {
            if ($entry->vat !== null) {
                $used[$entry->vat->category->value] = true;
            }
        }

        $declared = [];

        foreach ($document->taxSubtotals as $subtotal) {
            $declared[$subtotal->category->value] = true;
        }

        $errors = [];

        foreach (array_keys($used) as $category) {
            if (! isset($declared[$category])) {
                $errors[] = $this->error(self::RULE_IDS[$category], 'taxSubtotals', sprintf('Category %s is used on the document but has no VAT breakdown.', $category), ValidationSource::EN16931);
            }
        }

        foreach (array_keys($declared) as $category) {
            if (! isset($used[$category])) {
                $errors[] = $this->error(self::RULE_IDS[$category], 'taxSubtotals', sprintf('The VAT breakdown declares category %s but nothing on the document uses it.', $category), ValidationSource::EN16931);
            }
        }

        $standardRates = [];

        foreach ($document->taxSubtotals as $subtotal) {
            if ($subtotal->category === VatCategory::S && $subtotal->rate !== null) {
                $standardRates[$subtotal->rate->rounded(2)->toString()] = true;
            }
        }

        $lineRates = [];

        foreach ($document->lines as $line) {
            if ($line->vat->category === VatCategory::S && $line->vat->rate !== null) {
                $lineRates[$line->vat->rate->rounded(2)->toString()] = true;
            }
        }

        foreach (array_keys($lineRates) as $rate) {
            if (! isset($standardRates[$rate])) {
                $errors[] = $this->error('BR-S-08', 'taxSubtotals', sprintf('Lines are standard rated at %s %% but no VAT breakdown carries that rate.', $rate), ValidationSource::EN16931);
            }
        }

        $hasO = array_any($document->taxSubtotals, fn (TaxSubtotal $s) => $s->category === VatCategory::O);

        if ($hasO && count($declared) > 1) {
            $errors[] = $this->error('BR-O-11', 'taxSubtotals', 'A document with a "Not subject to VAT" breakdown cannot contain other breakdowns.', ValidationSource::EN16931);
        }

        if ($hasO && count($used) > 1) {
            $errors[] = $this->error('BR-O-12', 'lines', 'A document with a "Not subject to VAT" breakdown cannot have lines, allowances or charges in another category.', ValidationSource::EN16931);
        }

        return $errors;
    }
}
