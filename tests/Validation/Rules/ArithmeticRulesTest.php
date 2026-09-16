<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Document\AllowanceCharge;
use AtlasFlow\EFacturaRo\Document\LineVat;
use AtlasFlow\EFacturaRo\Document\TaxSubtotal;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Tests\Fixtures\Documents;
use AtlasFlow\EFacturaRo\Tests\Fixtures\Rebuild;
use AtlasFlow\EFacturaRo\Validation\LocalValidator;
use AtlasFlow\EFacturaRo\Validation\ValidationResult;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;
use AtlasFlow\EFacturaRo\Vat\VatCategory;

function rulesOn(object $document): ValidationResult
{
    return (new LocalValidator)->rules($document);
}

it('BR-CO-10: catches a line extension total that is not the sum of the lines', function () {
    $doc = Documents::standardInvoice();
    $bad = Rebuild::with($doc, ['totals' => Rebuild::with($doc->totals, ['lineExtensionAmount' => Amount::of('2500.01')])]);

    $result = rulesOn($bad);

    expect($result->has('BR-CO-10'))->toBeTrue()
        ->and($result->errors[0]->path)->toBe('totals.lineExtensionAmount')
        ->and($result->errors[0]->source)->toBe(ValidationSource::ARITHMETIC);
});

it('BR-CO-11/12: the allowance and charge totals must match the entries or be absent together', function () {
    $doc = Documents::standardInvoice();

    $wrongTotal = Rebuild::with($doc, ['totals' => Rebuild::with($doc->totals, ['allowanceTotal' => Amount::of('40.00')])]);
    $missingTotal = Rebuild::with($doc, ['totals' => Rebuild::with($doc->totals, ['allowanceTotal' => null])]);
    $phantomCharge = Rebuild::with($doc, ['totals' => Rebuild::with($doc->totals, ['chargeTotal' => Amount::of('5.00')])]);

    expect(rulesOn($wrongTotal)->has('BR-CO-11'))->toBeTrue()
        ->and(rulesOn($missingTotal)->has('BR-CO-11'))->toBeTrue()
        ->and(rulesOn($phantomCharge)->has('BR-CO-12'))->toBeTrue();
});

it('BR-CO-13/15/16: the chained totals must add up', function () {
    $doc = Documents::standardInvoiceWithPrepayment();
    $totals = $doc->totals;

    $exclusive = Rebuild::with($doc, ['totals' => Rebuild::with($totals, ['taxExclusiveAmount' => Amount::of('2450.10')])]);
    $inclusive = Rebuild::with($doc, ['totals' => Rebuild::with($totals, ['taxInclusiveAmount' => Amount::of('2944.00')])]);
    $payable = Rebuild::with($doc, ['totals' => Rebuild::with($totals, ['payableAmount' => Amount::of('1944.50')])]);

    expect(rulesOn($exclusive)->codes())->toContain('BR-CO-13')
        ->and(rulesOn($inclusive)->codes())->toContain('BR-CO-15')
        ->and(rulesOn($payable)->codes())->toBe(['BR-CO-16']);
});

it('BR-CO-14: the VAT total is the sum of the breakdown tax amounts', function () {
    $doc = Documents::standardInvoice();
    $bad = Rebuild::with($doc, ['totals' => Rebuild::with($doc->totals, ['taxAmount' => Amount::of('494.00')])]);

    expect(rulesOn($bad)->codes())->toContain('BR-CO-14');
});

it('BR-CO-17 / BR-S-09: a standard-rated tax amount within one unit of taxable × rate passes, beyond it fails', function () {
    $doc = Documents::creditNote();
    $subtotal = $doc->taxSubtotals[0];

    $drift = Rebuild::with($doc, [
        'taxSubtotals' => [Rebuild::with($subtotal, ['taxAmount' => Amount::of('26.99')])],
        'totals' => Rebuild::with($doc->totals, ['taxAmount' => Amount::of('26.99'), 'taxInclusiveAmount' => Amount::of('151.99'), 'payableAmount' => Amount::of('151.99')]),
    ]);
    $wrong = Rebuild::with($doc, [
        'taxSubtotals' => [Rebuild::with($subtotal, ['taxAmount' => Amount::of('30.00')])],
        'totals' => Rebuild::with($doc->totals, ['taxAmount' => Amount::of('30.00'), 'taxInclusiveAmount' => Amount::of('155.00'), 'payableAmount' => Amount::of('155.00')]),
    ]);

    expect(rulesOn($drift)->ok)->toBeTrue()
        ->and(rulesOn($wrong)->codes())->toBe(['BR-CO-17', 'BR-S-09']);
});

it('BR-S-08: a standard-rated breakdown must cover the lines and document-level entries at its rate', function () {
    $doc = Documents::standardInvoice();
    [$s21, $s11] = $doc->taxSubtotals;

    $bad = Rebuild::with($doc, ['taxSubtotals' => [Rebuild::with($s21, ['taxableAmount' => Amount::of('2300.00')]), $s11]]);

    expect(rulesOn($bad)->codes())->toContain('BR-S-08');
});

it('BR-IC-08 / BR-IC-09: an intra-community breakdown is exact and carries zero tax', function () {
    $doc = Documents::intraEuSupplyInEur();
    $k = $doc->taxSubtotals[0];

    $offByCent = Rebuild::with($doc, ['taxSubtotals' => [Rebuild::with($k, ['taxableAmount' => Amount::of('840.01')])]]);
    $taxed = Rebuild::with($doc, ['taxSubtotals' => [Rebuild::with($k, ['taxAmount' => Amount::of('1.00')])]]);

    expect(rulesOn($offByCent)->codes())->toContain('BR-IC-08')
        ->and(rulesOn($taxed)->codes())->toContain('BR-IC-09');
});

it('LINE-NET-AMOUNT: a line whose net amount is not quantity × price ± allowances is reported', function () {
    $doc = Documents::standardInvoice();
    $line = Rebuild::with($doc->lines[0], ['netAmount' => Amount::of('1250.50')]);
    $bad = Rebuild::with($doc, ['lines' => [$line, ...array_slice($doc->lines, 1)]]);

    $result = rulesOn($bad);

    expect($result->has('LINE-NET-AMOUNT'))->toBeTrue()
        ->and($result->errors[0]->path)->toBe('lines[0].netAmount');
});

it('LINE-NET-AMOUNT: line allowances and charges and a price base quantity are honoured', function () {
    $doc = Documents::creditNote();
    $line = Rebuild::with($doc->lines[0], [
        'unitPrice' => Amount::of('125.00'),
        'priceBaseQuantity' => Amount::of('10'),
        'netAmount' => Amount::of('120.00'),
        'allowancesCharges' => [AllowanceCharge::allowance('5.00', 'Discount')],
    ]);
    $good = Rebuild::with($doc, ['lines' => [$line]]);

    expect(rulesOn($good)->has('LINE-NET-AMOUNT'))->toBeFalse();
});

it('BR-DEC: money with three decimals is refused, prices and quantities are not', function () {
    $doc = Documents::creditNote();
    $threeDecimals = Rebuild::with($doc, ['totals' => Rebuild::with($doc->totals, ['payableAmount' => Amount::of('151.250')])]);
    $precisePrice = Rebuild::with($doc, ['lines' => [Rebuild::with($doc->lines[0], ['unitPrice' => Amount::of('12.5000')])]]);

    expect(rulesOn($threeDecimals)->has('BR-DEC-18'))->toBeTrue()
        ->and(rulesOn($precisePrice)->ok)->toBeTrue();
});

it('BR-Z-09 and friends: a non-standard breakdown with tax is refused', function () {
    $doc = Documents::exempt();
    $bad = Rebuild::with($doc, ['taxSubtotals' => [Rebuild::with($doc->taxSubtotals[0], ['taxAmount' => Amount::of('0.01')])]]);

    expect(rulesOn($bad)->codes())->toContain('BR-E-09');
});

it('category rates: S needs a positive rate, exempt-like need zero, O carries none', function () {
    $doc = Documents::creditNote();
    $line = $doc->lines[0];
    $subtotal = $doc->taxSubtotals[0];

    $zeroStandard = Rebuild::with($doc, [
        'lines' => [Rebuild::with($line, ['vat' => LineVat::standard('0')])],
        'taxSubtotals' => [Rebuild::with($subtotal, ['rate' => Amount::of('0')])],
    ]);
    $ratedExempt = Rebuild::with($doc, [
        'lines' => [Rebuild::with($line, ['vat' => new LineVat(VatCategory::E, Amount::of('5'), 'VATEX-EU-F')])],
        'taxSubtotals' => [new TaxSubtotal(VatCategory::E, Amount::of('5'), Amount::of('125.00'), Amount::of('0.00'), 'VATEX-EU-F')],
    ]);
    $ratedO = Rebuild::with($doc, [
        'lines' => [Rebuild::with($line, ['vat' => new LineVat(VatCategory::O, Amount::of('0'), 'VATEX-EU-O')])],
        'taxSubtotals' => [new TaxSubtotal(VatCategory::O, Amount::of('0'), Amount::of('125.00'), Amount::of('0.00'), 'VATEX-EU-O')],
    ]);

    expect(rulesOn($zeroStandard)->codes())->toContain('BR-S-05', 'BR-S-08')
        ->and(rulesOn($ratedExempt)->codes())->toContain('BR-E-05', 'BR-E-07')
        ->and(rulesOn($ratedO)->codes())->toContain('BR-O-05', 'BR-O-07');
});
