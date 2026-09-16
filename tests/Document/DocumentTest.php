<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Document\Address;
use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\DocumentType;
use AtlasFlow\EFacturaRo\Document\Line;
use AtlasFlow\EFacturaRo\Document\LineVat;
use AtlasFlow\EFacturaRo\Document\Party;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Support\Cnp;
use AtlasFlow\EFacturaRo\Support\Cui;
use AtlasFlow\EFacturaRo\Support\ForeignIdentifier;
use AtlasFlow\EFacturaRo\Tests\Fixtures\Documents;

it('builds every fixture document', function (string $name) {
    $document = Documents::all()[$name]();

    expect($document)->toBeInstanceOf(Document::class)
        ->and($document->customizationId)->toBe(Document::CIUS_RO_CUSTOMIZATION_ID);
})->with(array_keys(Documents::all()));

it('accepts a credit note without BT-25, as ANAF does, but says one is expected', function () {
    $base = Documents::standardInvoice();

    $creditNote = new Document(
        type: DocumentType::CREDIT_NOTE,
        number: 'X',
        issueDate: $base->issueDate,
        currency: 'RON',
        seller: $base->seller,
        buyer: $base->buyer,
        lines: $base->lines,
        taxSubtotals: $base->taxSubtotals,
        totals: $base->totals,
    );

    expect($creditNote->precedingDocuments)->toBe([])
        ->and($creditNote->type->expectsPrecedingDocuments())->toBeTrue()
        ->and(DocumentType::INVOICE->expectsPrecedingDocuments())->toBeFalse();
});

it('refuses a foreign-currency document without RON as tax currency', function () {
    $base = Documents::standardInvoice();

    new Document(
        type: DocumentType::INVOICE,
        number: 'X',
        issueDate: $base->issueDate,
        currency: 'EUR',
        seller: $base->seller,
        buyer: $base->buyer,
        lines: $base->lines,
        taxSubtotals: $base->taxSubtotals,
        totals: $base->totals,
    );
})->throws(InvalidArgumentException::class, 'BR-RO-030');

it('refuses a document without lines or without a VAT breakdown', function () {
    $base = Documents::standardInvoice();

    $build = fn (array $lines, array $subtotals) => new Document(
        type: DocumentType::INVOICE,
        number: 'X',
        issueDate: $base->issueDate,
        currency: 'RON',
        seller: $base->seller,
        buyer: $base->buyer,
        lines: $lines,
        taxSubtotals: $subtotals,
        totals: $base->totals,
    );

    expect(fn () => $build([], $base->taxSubtotals))->toThrow(InvalidArgumentException::class, 'BR-16')
        ->and(fn () => $build($base->lines, []))->toThrow(InvalidArgumentException::class, 'BR-CO-18');
});

it('refuses a negative unit price on a line', function () {
    new Line('1', 'x', Amount::of('1'), Amount::of('-1'), Amount::of('-1'), LineVat::standard('21'));
})->throws(InvalidArgumentException::class, 'BR-27');

it('writes the RO prefix only for VAT-registered parties', function () {
    $address = new Address('Str. X 1', 'Sibiu', 'RO', 'RO-SB');

    $registered = new Party('A', $address, Cui::of('12345674'), vatRegistered: true);
    $unregistered = new Party('B', $address, Cui::of('12345674'), vatRegistered: false);
    $foreign = new Party('C', new Address('X', 'Wien', 'AT'), ForeignIdentifier::of('ATU12345678', 'AT'), vatRegistered: true);

    expect($registered->vatIdentifier())->toBe('RO12345674')
        ->and($registered->legalRegistrationIdentifier())->toBeNull()
        ->and($unregistered->vatIdentifier())->toBeNull()
        ->and($unregistered->legalRegistrationIdentifier())->toBe('12345674')
        ->and($foreign->vatIdentifier())->toBe('ATU12345678');
});

it('gives a consumer without a CNP the CIUS-RO placeholder', function () {
    $address = new Address('Str. X 1', 'Sibiu', 'RO', 'RO-SB');

    $withCnp = Party::consumer('Ion', $address, Cnp::of('1800101221144'));
    $without = Party::consumer('Maria', $address);

    expect($withCnp->isConsumer)->toBeTrue()
        ->and($withCnp->legalRegistrationIdentifier())->toBe('1800101221144')
        ->and($without->legalRegistrationIdentifier())->toBe(Cnp::PLACEHOLDER)
        ->and($without->vatIdentifier())->toBeNull();
});

it('knows when the buyer is a consumer or foreign', function () {
    expect(Documents::b2cWithCnp()->isB2C())->toBeTrue()
        ->and(Documents::standardInvoice()->isB2C())->toBeFalse()
        ->and(Documents::intraEuSupplyInEur()->hasForeignBuyer())->toBeTrue()
        ->and(Documents::standardInvoice()->hasForeignBuyer())->toBeFalse();
});
