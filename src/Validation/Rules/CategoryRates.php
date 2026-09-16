<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\LineVat;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationError;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;
use AtlasFlow\EFacturaRo\Vat\VatCategory;

/**
 * The rate rules per category, on lines, document-level allowances and
 * charges, and breakdowns:
 * - BR-S-05/06/07 and BR-S-08's premise: Standard rated needs a rate > 0;
 * - BR-Z-05..07, BR-E-05..07, BR-AE-05..07, BR-IC-05..07, BR-G-05..07:
 *   Zero rated, Exempt, Reverse charge, Intra-community and Export carry
 *   a rate of 0;
 * - BR-O-05..07: Not subject to VAT carries no rate at all;
 * - BR-48: every breakdown has a rate unless its category is O.
 */
final class CategoryRates implements Rule
{
    use Concerns;

    private const array RULE_IDS = [
        'S' => 'BR-S-05', 'Z' => 'BR-Z-05', 'E' => 'BR-E-05', 'AE' => 'BR-AE-05', 'K' => 'BR-IC-05', 'G' => 'BR-G-05', 'O' => 'BR-O-05',
    ];

    public function check(Document $document): array
    {
        $errors = [];

        foreach ($document->lines as $index => $line) {
            $errors = [...$errors, ...$this->checkVat($line->vat, sprintf('lines[%d].vat.rate', $index))];
        }

        foreach ($document->allowancesCharges as $index => $entry) {
            if ($entry->vat !== null) {
                $errors = [...$errors, ...$this->checkVat($entry->vat, sprintf('allowancesCharges[%d].vat.rate', $index))];
            }
        }

        foreach ($document->taxSubtotals as $index => $subtotal) {
            $path = sprintf('taxSubtotals[%d].rate', $index);

            if ($subtotal->category === VatCategory::O) {
                if ($subtotal->rate !== null) {
                    $errors[] = $this->error('BR-O-07', $path, 'A "Not subject to VAT" breakdown must not carry a rate.', ValidationSource::EN16931);
                }

                continue;
            }

            if ($subtotal->rate === null) {
                $errors[] = $this->error('BR-48', $path, 'Every VAT breakdown needs a rate unless its category is O.', ValidationSource::EN16931);
            } elseif ($subtotal->category === VatCategory::S && ! $subtotal->rate->isPositive()) {
                $errors[] = $this->error('BR-S-08', $path, 'A Standard rated breakdown needs a rate above zero.', ValidationSource::EN16931);
            } elseif ($subtotal->category->rateMustBeZero() && ! $subtotal->rate->isZero()) {
                $errors[] = $this->error(str_replace('-05', '-07', self::RULE_IDS[$subtotal->category->value] ?? 'BR-48'), $path, sprintf('A breakdown in category %s must carry a rate of 0.', $subtotal->category->value), ValidationSource::EN16931);
            }
        }

        return $errors;
    }

    /** @return list<ValidationError> */
    private function checkVat(LineVat $vat, string $path): array
    {
        if ($vat->category === VatCategory::O) {
            return $vat->rate === null ? [] : [$this->error('BR-O-05', $path, 'A "Not subject to VAT" line must not carry a rate.', ValidationSource::EN16931)];
        }

        if ($vat->rate === null) {
            return [$this->error(self::RULE_IDS[$vat->category->value] ?? 'BR-48', $path, sprintf('A line in category %s needs a rate.', $vat->category->value), ValidationSource::EN16931)];
        }

        if ($vat->category === VatCategory::S) {
            return $vat->rate->isPositive() ? [] : [$this->error('BR-S-05', $path, 'A Standard rated line needs a rate above zero.', ValidationSource::EN16931)];
        }

        if ($vat->category->rateMustBeZero() && ! $vat->rate->isZero()) {
            return [$this->error(self::RULE_IDS[$vat->category->value], $path, sprintf('A line in category %s must carry a rate of 0.', $vat->category->value), ValidationSource::EN16931)];
        }

        return [];
    }
}
