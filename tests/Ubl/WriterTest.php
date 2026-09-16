<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Tests\Fixtures\Documents;
use AtlasFlow\EFacturaRo\Ubl\UblWriter;

function xpathOn(string $xml): DOMXPath
{
    $dom = new DOMDocument;
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

    return $xpath;
}

it('validates every fixture against the OASIS UBL 2.1 schema', function (string $name) {
    $xml = (new UblWriter)->write(Documents::all()[$name]());

    $dom = new DOMDocument;
    $dom->loadXML($xml);
    $schema = str_contains($xml, 'CreditNote-2') ? 'UBL-CreditNote-2.1.xsd' : 'UBL-Invoice-2.1.xsd';

    libxml_use_internal_errors(true);
    $ok = $dom->schemaValidate(__DIR__.'/../../resources/xsd/maindoc/'.$schema);
    $errors = array_map(fn ($e) => trim($e->message), libxml_get_errors());
    libxml_clear_errors();

    expect($ok)->toBeTrue(implode("\n", $errors));
})->with(array_keys(Documents::all()));

it('writes the CIUS-RO header', function () {
    $xpath = xpathOn((new UblWriter)->write(Documents::standardInvoice()));

    expect($xpath->evaluate('string(/*/cbc:CustomizationID)'))->toBe(Document::CIUS_RO_CUSTOMIZATION_ID)
        ->and($xpath->evaluate('string(/*/cbc:ProfileID)'))->toBe(Document::PROFILE_ID)
        ->and($xpath->evaluate('string(/*/cbc:UBLVersionID)'))->toBe('2.1')
        ->and($xpath->evaluate('string(/*/cbc:InvoiceTypeCode)'))->toBe('380')
        ->and($xpath->evaluate('local-name(/*)'))->toBe('Invoice');
});

it('writes a 381 as a CreditNote with the due date under PaymentMeans', function () {
    $xpath = xpathOn((new UblWriter)->write(Documents::creditNote()));

    expect($xpath->evaluate('local-name(/*)'))->toBe('CreditNote')
        ->and($xpath->evaluate('string(/*/cbc:CreditNoteTypeCode)'))->toBe('381')
        ->and($xpath->evaluate('count(/*/cbc:DueDate)'))->toBe(0.0)
        ->and($xpath->evaluate('string(/*/cac:PaymentMeans/cbc:PaymentDueDate)'))->toBe('2026-10-14')
        ->and($xpath->evaluate('string(/*/cac:CreditNoteLine/cbc:CreditedQuantity)'))->toBe('10')
        ->and($xpath->evaluate('string(/*/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID)'))->toBe('PV-2026-000123');
});

it('identifies a VAT-registered party by a prefixed VAT id and a bare legal id', function () {
    $xpath = xpathOn((new UblWriter)->write(Documents::standardInvoice()));
    $seller = '/*/cac:AccountingSupplierParty/cac:Party';
    $buyer = '/*/cac:AccountingCustomerParty/cac:Party';

    expect($xpath->evaluate("string($seller/cac:PartyTaxScheme/cbc:CompanyID)"))->toBe('RO12345674')
        ->and($xpath->evaluate("string($seller/cac:PartyTaxScheme/cac:TaxScheme/cbc:ID)"))->toBe('VAT')
        ->and($xpath->evaluate("string($seller/cac:PartyLegalEntity/cbc:CompanyID)"))->toBe('12345674')
        ->and($xpath->evaluate("string($seller/cac:PartyLegalEntity/cbc:CompanyLegalForm)"))->toBe('J12/345/2015')
        ->and($xpath->evaluate("string($seller/cbc:EndpointID/@schemeID)"))->toBe('EM')
        ->and($xpath->evaluate("string($buyer/cac:PartyTaxScheme/cbc:CompanyID)"))->toBe('RO40000000')
        ->and($xpath->evaluate("count($buyer/cac:PartyLegalEntity/cbc:CompanyLegalForm)"))->toBe(0.0);
});

it('identifies a non-registered seller through a non-VAT tax scheme plus the legal id', function () {
    $xpath = xpathOn((new UblWriter)->write(Documents::exempt()));
    $seller = '/*/cac:AccountingSupplierParty/cac:Party';

    expect($xpath->evaluate("string($seller/cac:PartyTaxScheme/cbc:CompanyID)"))->toBe('11223342')
        ->and($xpath->evaluate("string($seller/cac:PartyTaxScheme/cac:TaxScheme/cbc:ID)"))->toBe(UblWriter::NON_VAT_TAX_SCHEME)
        ->and($xpath->evaluate("string($seller/cac:PartyLegalEntity/cbc:CompanyID)"))->toBe('11223342');
});

it('identifies a consumer by CNP or the placeholder and never by a tax scheme', function () {
    $writer = new UblWriter;
    $buyer = '/*/cac:AccountingCustomerParty/cac:Party';

    $withCnp = xpathOn($writer->write(Documents::b2cWithCnp()));
    $without = xpathOn($writer->write(Documents::b2cWithoutCnp()));

    expect($withCnp->evaluate("string($buyer/cac:PartyLegalEntity/cbc:CompanyID)"))->toBe('1800101221144')
        ->and($withCnp->evaluate("count($buyer/cac:PartyTaxScheme)"))->toBe(0.0)
        ->and($without->evaluate("string($buyer/cac:PartyLegalEntity/cbc:CompanyID)"))->toBe('0000000000000');
});

it('restates the VAT total in RON for a foreign-currency document', function () {
    $xpath = xpathOn((new UblWriter)->write(Documents::intraEuSupplyInEur()));

    expect($xpath->evaluate('string(/*/cbc:DocumentCurrencyCode)'))->toBe('EUR')
        ->and($xpath->evaluate('string(/*/cbc:TaxCurrencyCode)'))->toBe('RON')
        ->and($xpath->evaluate('count(/*/cac:TaxTotal)'))->toBe(2.0)
        ->and($xpath->evaluate('string(/*/cac:TaxTotal[2]/cbc:TaxAmount/@currencyID)'))->toBe('RON')
        ->and($xpath->evaluate('string(/*/cac:TaxTotal[1]/cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReasonCode)'))->toBe('VATEX-EU-IC')
        ->and($xpath->evaluate('count(/*/cac:InvoiceLine/cac:Item/cac:ClassifiedTaxCategory/cbc:TaxExemptionReasonCode)'))->toBe(0.0);
});

it('writes amounts exactly as supplied and is deterministic', function () {
    $writer = new UblWriter;
    $document = Documents::standardInvoice();
    $xml = $writer->write($document);
    $xpath = xpathOn($xml);

    expect($xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:PayableAmount)'))->toBe('2944.50')
        ->and($xpath->evaluate('string(/*/cac:InvoiceLine[1]/cac:Price/cbc:PriceAmount)'))->toBe('12.50')
        ->and($xpath->evaluate('string(/*/cac:InvoiceLine[1]/cbc:InvoicedQuantity/@unitCode)'))->toBe('H87')
        ->and($xpath->evaluate('string(/*/cac:AllowanceCharge/cbc:ChargeIndicator)'))->toBe('false')
        ->and($writer->write($document))->toBe($xml);
});
