<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationError;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/**
 * BR-DEC-01..28 (and their BR-DEC-RO twins): every money amount carries at
 * most two decimals — line net amounts, allowance and charge amounts and
 * bases, breakdown amounts and every document total. Prices (BT-146) and
 * quantities (BT-129) are exempt and may carry more.
 */
final class DecimalPlaces implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        $errors = [];
        $t = $document->totals;

        $check = function (?Amount $amount, string $code, string $path) use (&$errors): void {
            if ($amount !== null && $amount->scale() > 2) {
                $errors[] = $this->error($code, $path, sprintf('%s carries %d decimals; at most 2 are allowed.', $amount, $amount->scale()), ValidationSource::ARITHMETIC);
            }
        };

        $check($t->lineExtensionAmount, 'BR-DEC-09', 'totals.lineExtensionAmount');
        $check($t->allowanceTotal, 'BR-DEC-10', 'totals.allowanceTotal');
        $check($t->chargeTotal, 'BR-DEC-11', 'totals.chargeTotal');
        $check($t->taxExclusiveAmount, 'BR-DEC-12', 'totals.taxExclusiveAmount');
        $check($t->taxAmount, 'BR-DEC-13', 'totals.taxAmount');
        $check($t->taxInclusiveAmount, 'BR-DEC-14', 'totals.taxInclusiveAmount');
        $check($document->taxTotalInTaxCurrency, 'BR-DEC-15', 'taxTotalInTaxCurrency');
        $check($t->prepaidAmount, 'BR-DEC-16', 'totals.prepaidAmount');
        $check($t->payableRoundingAmount, 'BR-DEC-17', 'totals.payableRoundingAmount');
        $check($t->payableAmount, 'BR-DEC-18', 'totals.payableAmount');

        foreach ($document->taxSubtotals as $i => $subtotal) {
            $check($subtotal->taxableAmount, 'BR-DEC-19', "taxSubtotals[$i].taxableAmount");
            $check($subtotal->taxAmount, 'BR-DEC-20', "taxSubtotals[$i].taxAmount");
        }

        foreach ($document->allowancesCharges as $i => $entry) {
            $check($entry->amount, $entry->isCharge ? 'BR-DEC-05' : 'BR-DEC-01', "allowancesCharges[$i].amount");
            $check($entry->baseAmount, $entry->isCharge ? 'BR-DEC-06' : 'BR-DEC-02', "allowancesCharges[$i].baseAmount");
        }

        foreach ($document->lines as $i => $line) {
            $check($line->netAmount, 'BR-DEC-23', "lines[$i].netAmount");

            foreach ($line->allowancesCharges as $j => $entry) {
                $check($entry->amount, $entry->isCharge ? 'BR-DEC-27' : 'BR-DEC-24', "lines[$i].allowancesCharges[$j].amount");
                $check($entry->baseAmount, $entry->isCharge ? 'BR-DEC-28' : 'BR-DEC-25', "lines[$i].allowancesCharges[$j].baseAmount");
            }
        }

        /** @var list<ValidationError> $errors */
        return $errors;
    }
}
