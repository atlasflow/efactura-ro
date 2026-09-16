<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Document\Address;
use AtlasFlow\EFacturaRo\Document\Delivery;
use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\LineVat;
use AtlasFlow\EFacturaRo\Document\Party;
use AtlasFlow\EFacturaRo\Document\TaxSubtotal;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Support\Cui;
use AtlasFlow\EFacturaRo\Support\ForeignIdentifier;
use AtlasFlow\EFacturaRo\Tests\Fixtures\Documents;
use AtlasFlow\EFacturaRo\Tests\Fixtures\Rebuild;
use AtlasFlow\EFacturaRo\Validation\LocalValidator;
use AtlasFlow\EFacturaRo\Validation\Rules\BrCo10LineExtensionAmount;
use AtlasFlow\EFacturaRo\Validation\ValidationResult;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;
use AtlasFlow\EFacturaRo\Vat\VatCategory;

function check(object $document): ValidationResult
{
    return (new LocalValidator)->rules($document);
}

it('BR-S-01 and siblings: every used category has a breakdown and vice versa', function () {
    $doc = Documents::standardInvoice();

    $missing = Rebuild::with($doc, ['taxSubtotals' => [$doc->taxSubtotals[0]]]);
    $unused = Rebuild::with($doc, ['taxSubtotals' => [...$doc->taxSubtotals, new TaxSubtotal(VatCategory::Z, Amount::of('0'), Amount::of('0.00'), Amount::of('0.00'))]]);

    expect(check($missing)->codes())->toContain('BR-S-08')
        ->and(check($unused)->codes())->toContain('BR-Z-01');
});

it('BR-O-11/12: a "not subject to VAT" breakdown must stand alone', function () {
    $doc = Documents::creditNote();
    $mixed = Rebuild::with($doc, [
        'taxSubtotals' => [...$doc->taxSubtotals, new TaxSubtotal(VatCategory::O, null, Amount::of('0.00'), Amount::of('0.00'), 'VATEX-EU-O')],
    ]);

    expect(check($mixed)->codes())->toContain('BR-O-11');
});

it('BR-E-10 / BR-S-10: exemption reasons are required on exempt-like breakdowns and forbidden on S and Z', function () {
    $exempt = Documents::exempt();
    $noReason = Rebuild::with($exempt, ['taxSubtotals' => [Rebuild::with($exempt->taxSubtotals[0], ['exemptionCode' => null, 'exemptionReason' => null])]]);

    $standard = Documents::creditNote();
    $withReason = Rebuild::with($standard, ['taxSubtotals' => [Rebuild::with($standard->taxSubtotals[0], ['exemptionReason' => 'why?'])]]);

    $unknownCode = Rebuild::with($exempt, ['taxSubtotals' => [Rebuild::with($exempt->taxSubtotals[0], ['exemptionCode' => 'VATEX-RO-INVENTED'])]]);

    expect(check($noReason)->codes())->toBe(['BR-E-10'])
        ->and(check($withReason)->codes())->toBe(['BR-S-10'])
        ->and(check($unknownCode)->codes())->toBe(['BR-CL-22']);
});

it('BR-CO-25: a positive payable amount needs a due date or payment terms, except on credit notes', function () {
    $invoice = Documents::b2cWithCnp();
    $bare = Rebuild::with($invoice, ['dueDate' => null, 'paymentTerms' => null]);
    $terms = Rebuild::with($invoice, ['dueDate' => null, 'paymentTerms' => 'La livrare']);

    $creditNote = Rebuild::with(Documents::creditNote(), ['dueDate' => null]);

    expect(check($bare)->codes())->toBe(['BR-CO-25'])
        ->and(check($terms)->ok)->toBeTrue()
        ->and(check($creditNote)->ok)->toBeTrue();
});

it('BR-IC-11/12: an intra-community supply needs a delivery date (or period) and a delivery address', function () {
    $doc = Documents::intraEuSupplyInEur();

    $noDelivery = Rebuild::with($doc, ['delivery' => null]);
    $noDate = Rebuild::with($doc, ['delivery' => new Delivery(null, $doc->delivery?->address)]);

    expect(check($noDelivery)->codes())->toBe(['BR-IC-11', 'BR-IC-12'])
        ->and(check($noDate)->codes())->toBe(['BR-IC-11']);
});

it('identity: a seller without an identifier, or a Romanian party with a bad CUI, is refused', function () {
    $doc = Documents::standardInvoice();

    $anonymous = Rebuild::with($doc, ['seller' => Rebuild::with($doc->seller, ['taxIdentifier' => null, 'vatRegistered' => false])]);
    $badCui = Rebuild::with($doc, ['buyer' => Rebuild::with($doc->buyer, ['taxIdentifier' => ForeignIdentifier::of('RO987456123', 'RO')])]);
    $foreignIdAtHome = Rebuild::with($doc, ['buyer' => Rebuild::with($doc->buyer, ['taxIdentifier' => ForeignIdentifier::of('DE123456789', 'DE')])]);

    expect(check($anonymous)->codes())->toBe(['BR-CO-26'])
        ->and(check($badCui)->codes())->toBe(['ERRIdentif'])
        ->and(check($badCui)->errors[0]->source)->toBe(ValidationSource::IDENTITY)
        ->and(check($foreignIdAtHome)->codes())->toBe(['ERRIdentif']);
});

it('identity: a consumer needs no identifier and a foreign buyer is fine', function () {
    expect(check(Documents::b2cWithoutCnp())->ok)->toBeTrue()
        ->and(check(Documents::intraEuSupplyInEur())->ok)->toBeTrue();
});

it('BR-CO-09: a foreign VAT identifier must start with its country code', function () {
    $doc = Documents::intraEuSupplyInEur();
    $bad = Rebuild::with($doc, ['buyer' => Rebuild::with($doc->buyer, ['taxIdentifier' => ForeignIdentifier::of('123456789', 'DE')])]);

    expect(check($bad)->codes())->toBe(['BR-CO-09']);
});

it('BR-RO-001 and BR-RO-010: the customisation id and a digit in the number', function () {
    $doc = Documents::creditNote();

    $oldCius = Rebuild::with($doc, ['customizationId' => str_replace('1.0.1', '1.0.0', Document::CIUS_RO_CUSTOMIZATION_ID)]);
    $noDigit = Rebuild::with($doc, ['number' => 'PVC-ABC']);

    expect(check($oldCius)->codes())->toBe(['BR-RO-001'])
        ->and(check($noDigit)->codes())->toBe(['BR-RO-010']);
});

it('BR-RO-100/110: Romanian addresses need a county code and Bucharest a sector', function () {
    $doc = Documents::creditNote();
    $seller = $doc->seller;

    $noCounty = Rebuild::with($doc, ['seller' => Rebuild::with($seller, ['address' => new Address('Str. X 1', 'Cluj-Napoca', 'RO', null)])]);
    $badCounty = Rebuild::with($doc, ['seller' => Rebuild::with($seller, ['address' => new Address('Str. X 1', 'Cluj-Napoca', 'RO', 'CJ')])]);
    $bucharest = Rebuild::with($doc, ['buyer' => Rebuild::with($doc->buyer, ['address' => new Address('Bd. Unirii 5', 'București', 'RO', 'RO-B')])]);
    $emptyLine = Rebuild::with($doc, ['buyer' => Rebuild::with($doc->buyer, ['address' => new Address('', 'SECTOR1', 'RO', 'RO-B')])]);

    expect(check($noCounty)->codes())->toBe(['BR-RO-110'])
        ->and(check($badCounty)->codes())->toBe(['BR-RO-110'])
        ->and(check($bucharest)->codes())->toBe(['BR-RO-101'])
        ->and(check($emptyLine)->codes())->toBe(['BR-RO-082']);
});

it('BR-RO-211: a delivery address needs a subdivision whatever its country', function () {
    $doc = Documents::intraEuSupplyInEur();
    $bad = Rebuild::with($doc, ['delivery' => new Delivery($doc->delivery?->actualDate, new Address('Hauptstraße 1', 'München', 'DE'))]);

    expect(check($bad)->codes())->toBe(['BR-RO-211']);
});

it('BR-RO-L*: length and cardinality limits', function () {
    $doc = Documents::creditNote();

    $longName = Rebuild::with($doc, ['lines' => [Rebuild::with($doc->lines[0], ['name' => str_repeat('x', 101)])]]);
    $longCity = Rebuild::with($doc, ['seller' => Rebuild::with($doc->seller, ['address' => new Address('Str. X', str_repeat('y', 51), 'RO', 'RO-CJ')])]);
    $manyNotes = Rebuild::with($doc, ['notes' => array_fill(0, 21, 'n')]);
    $longTerms = Rebuild::with($doc, ['paymentTerms' => str_repeat('t', 301)]);

    expect(check($longName)->codes())->toBe(['BR-RO-L1024'])
        ->and(check($longCity)->codes())->toBe(['BR-RO-L0501'])
        ->and(check($manyNotes)->codes())->toBe(['BR-RO-A020'])
        ->and(check($longTerms)->codes())->toBe(['BR-RO-L301']);
});

it('reports paths on the model, not XPaths, for model-level rules', function () {
    $doc = Documents::creditNote();
    $bad = Rebuild::with($doc, ['lines' => [Rebuild::with($doc->lines[0], ['vat' => LineVat::standard('0')])]]);

    $paths = array_map(fn ($e) => $e->path, check($bad)->errors);

    expect($paths)->toContain('lines[0].vat.rate');
});

it('lets a consumer pass a narrower rule list', function () {
    $doc = Documents::standardInvoice();
    $bad = Rebuild::with($doc, ['number' => 'NO-DIGITS']);

    $onlyArithmetic = new LocalValidator(rules: [new BrCo10LineExtensionAmount]);

    expect($onlyArithmetic->rules($bad)->ok)->toBeTrue()
        ->and((new LocalValidator)->rules($bad)->ok)->toBeFalse();
});

it('treats a party with no address line as still identifiable by CUI', function () {
    $party = new Party('X', new Address('', '', 'RO', 'RO-CJ'), Cui::of('12345674'), true);
    $doc = Rebuild::with(Documents::creditNote(), ['seller' => $party]);

    expect(check($doc)->codes())->toBe(['BR-RO-081', 'BR-RO-091']);
});
