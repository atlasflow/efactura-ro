<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Tests\Fixtures\Documents;
use AtlasFlow\EFacturaRo\Ubl\UblReader;
use AtlasFlow\EFacturaRo\Ubl\UblWriter;

it('writes, reads and writes again to the same bytes', function (string $name) {
    $writer = new UblWriter;
    $reader = new UblReader;

    $document = Documents::all()[$name]();
    $first = $writer->write($document);
    $again = $writer->write($reader->read($first));

    expect($again)->toBe($first);
})->with(array_keys(Documents::all()));

it('restores the parties, lines and totals faithfully', function () {
    $document = Documents::standardInvoice();
    $read = (new UblReader)->read((new UblWriter)->write($document));

    expect($read->type)->toBe($document->type)
        ->and($read->number)->toBe('PV-2026-000123')
        ->and($read->issueDate->format('Y-m-d'))->toBe('2026-09-10')
        ->and($read->dueDate?->format('Y-m-d'))->toBe('2026-10-10')
        ->and($read->seller->cui()?->digits())->toBe('12345674')
        ->and($read->seller->vatRegistered)->toBeTrue()
        ->and($read->seller->registrationNumber)->toBe('J12/345/2015')
        ->and($read->buyer->cui()?->digits())->toBe('40000000')
        ->and($read->buyer->address->city)->toBe('SECTOR3')
        ->and($read->buyer->address->countySubdivision)->toBe('RO-B')
        ->and($read->lines)->toHaveCount(4)
        ->and($read->lines[0]->netAmount->toString())->toBe('1250.00')
        ->and($read->lines[3]->unitCode)->toBe('KGM')
        ->and($read->lines[3]->vat->rate?->toString())->toBe('11')
        ->and($read->taxSubtotals)->toHaveCount(2)
        ->and($read->totals->allowanceTotal?->toString())->toBe('50.00')
        ->and($read->totals->payableAmount->toString())->toBe('2944.50')
        ->and($read->allowancesCharges[0]->reasonCode)->toBe('95')
        ->and($read->paymentMeans[0]->iban)->toBe('RO49AAAA1B31007593840000')
        ->and($read->notes)->toBe(['Marfa a fost livrată în bună stare.']);
});

it('reads a credit note with its preceding document and due date', function () {
    $read = (new UblReader)->read((new UblWriter)->write(Documents::creditNote()));

    expect($read->isCreditNote())->toBeTrue()
        ->and($read->precedingDocuments[0]->number)->toBe('PV-2026-000123')
        ->and($read->precedingDocuments[0]->issueDate?->format('Y-m-d'))->toBe('2026-09-10')
        ->and($read->dueDate?->format('Y-m-d'))->toBe('2026-10-14');
});

it('reads consumers, foreign buyers and non-registered sellers back into the right shapes', function () {
    $reader = new UblReader;
    $writer = new UblWriter;

    $withCnp = $reader->read($writer->write(Documents::b2cWithCnp()));
    $without = $reader->read($writer->write(Documents::b2cWithoutCnp()));
    $foreign = $reader->read($writer->write(Documents::intraEuSupplyInEur()));
    $exempt = $reader->read($writer->write(Documents::exempt()));

    expect($withCnp->buyer->isConsumer)->toBeTrue()
        ->and($withCnp->buyer->cnp()?->digits())->toBe('1800101221144')
        ->and($without->buyer->isConsumer)->toBeTrue()
        ->and($without->buyer->taxIdentifier)->toBeNull()
        ->and($foreign->buyer->taxIdentifier?->value())->toBe('DE123456789')
        ->and($foreign->buyer->taxIdentifier?->country())->toBe('DE')
        ->and($foreign->buyer->vatRegistered)->toBeTrue()
        ->and($foreign->taxCurrency)->toBe('RON')
        ->and($foreign->taxTotalInTaxCurrency?->toString())->toBe('0.00')
        ->and($foreign->delivery?->actualDate?->format('Y-m-d'))->toBe('2026-09-11')
        ->and($exempt->seller->vatRegistered)->toBeFalse()
        ->and($exempt->seller->cui()?->digits())->toBe('11223342')
        ->and($exempt->taxSubtotals[0]->exemptionCode)->toBe('VATEX-EU-F');
});

it('reads the Ministry of Finance sample invoice and credit note', function () {
    $reader = new UblReader;

    $invoice = $reader->read(fixtureFile('mf/eInvoice_ex.xml'));
    $creditNote = $reader->read(fixtureFile('mf/creditNote_ex.xml'));

    expect($invoice->number)->toBe('6422451356')
        ->and($invoice->lines)->toHaveCount(35)
        ->and($invoice->seller->taxIdentifier?->value())->toBe('RO1234567890')
        ->and($invoice->buyer->identifier)->toBe('123456')
        ->and($invoice->totals->payableAmount->toString())->toBe('41340576.71')
        ->and($creditNote->isCreditNote())->toBeTrue()
        ->and($creditNote->lines)->toHaveCount(1);
});

it('refuses XML that is not an invoice', function () {
    (new UblReader)->read('<Order xmlns="urn:x"/>');
})->throws(InvalidArgumentException::class, 'Not a UBL Invoice');

it('refuses malformed XML with the parser message', function () {
    (new UblReader)->read('<Invoice');
})->throws(InvalidArgumentException::class, 'Cannot parse');
