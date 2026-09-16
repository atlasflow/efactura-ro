<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Tests\Fixtures;

use AtlasFlow\EFacturaRo\Document\Address;
use AtlasFlow\EFacturaRo\Document\AllowanceCharge;
use AtlasFlow\EFacturaRo\Document\Contact;
use AtlasFlow\EFacturaRo\Document\Delivery;
use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\DocumentType;
use AtlasFlow\EFacturaRo\Document\Endpoint;
use AtlasFlow\EFacturaRo\Document\Line;
use AtlasFlow\EFacturaRo\Document\LineVat;
use AtlasFlow\EFacturaRo\Document\Party;
use AtlasFlow\EFacturaRo\Document\PaymentMeans;
use AtlasFlow\EFacturaRo\Document\PrecedingDocument;
use AtlasFlow\EFacturaRo\Document\TaxSubtotal;
use AtlasFlow\EFacturaRo\Document\Totals;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Support\Cnp;
use AtlasFlow\EFacturaRo\Support\Cui;
use AtlasFlow\EFacturaRo\Support\ForeignIdentifier;
use AtlasFlow\EFacturaRo\Vat\RomanianVatMapping;
use AtlasFlow\EFacturaRo\Vat\VatCategory;
use AtlasFlow\EFacturaRo\Vat\VatTreatment;
use DateTimeImmutable;

/**
 * The documents every suite runs against: the writer, the reader, the rules
 * and the live validare test. Amounts are worked out by hand and written as
 * strings — the kernel never computes them. All identifiers are synthetic
 * (valid check digits, no real company).
 */
final class Documents
{
    /** @return array<string, callable(): Document> */
    public static function all(): array
    {
        return [
            'standard-invoice' => self::standardInvoice(...),
            'standard-invoice-with-prepayment' => self::standardInvoiceWithPrepayment(...),
            'credit-note' => self::creditNote(...),
            'intra-eu-supply-eur' => self::intraEuSupplyInEur(...),
            'reverse-charge' => self::reverseCharge(...),
            'b2c-with-cnp' => self::b2cWithCnp(...),
            'b2c-without-cnp' => self::b2cWithoutCnp(...),
            'exempt' => self::exempt(...),
        ];
    }

    public static function seller(): Party
    {
        return new Party(
            name: 'Pepiniera Verde SRL',
            address: new Address('Str. Florilor 10', 'Cluj-Napoca', 'RO', 'RO-CJ', '400001'),
            taxIdentifier: Cui::of('RO12345674'),
            vatRegistered: true,
            registrationNumber: 'J12/345/2015',
            contact: new Contact('Ana Pop', 'facturi@pepiniera-verde.example', '+40 264 000 000'),
            endpoint: Endpoint::email('facturi@pepiniera-verde.example'),
        );
    }

    public static function buyer(): Party
    {
        return new Party(
            name: 'Grădina Albastră SRL',
            address: new Address('Bd. Unirii 5', 'SECTOR3', 'RO', 'RO-B', '030167'),
            taxIdentifier: Cui::of('40000000'),
            vatRegistered: true,
            registrationNumber: 'J40/1000/2018',
        );
    }

    public static function standardInvoice(): Document
    {
        $s21 = LineVat::standard('21');
        $s11 = LineVat::standard('11');

        return new Document(
            type: DocumentType::INVOICE,
            number: 'PV-2026-000123',
            issueDate: new DateTimeImmutable('2026-09-10'),
            currency: 'RON',
            seller: self::seller(),
            buyer: self::buyer(),
            lines: [
                new Line('1', 'Buxus sempervirens 30-40 cm, ghiveci 3 l', Amount::of('100'), Amount::of('12.50'), Amount::of('1250.00'), $s21, sellerItemId: 'BUX-3040'),
                new Line('2', 'Thuja occidentalis Smaragd 80-100 cm', Amount::of('20'), Amount::of('45.00'), Amount::of('900.00'), $s21),
                new Line('3', 'Transport', Amount::of('1'), Amount::of('150.00'), Amount::of('150.00'), $s21, description: 'Livrare Cluj-Napoca – București'),
                new Line('4', 'Îngrășământ organic 25 kg', Amount::of('10'), Amount::of('20.00'), Amount::of('200.00'), $s11, unitCode: 'KGM'),
            ],
            taxSubtotals: [
                new TaxSubtotal(VatCategory::S, Amount::of('21'), Amount::of('2250.00'), Amount::of('472.50')),
                new TaxSubtotal(VatCategory::S, Amount::of('11'), Amount::of('200.00'), Amount::of('22.00')),
            ],
            totals: new Totals(
                lineExtensionAmount: Amount::of('2500.00'),
                taxExclusiveAmount: Amount::of('2450.00'),
                taxAmount: Amount::of('494.50'),
                taxInclusiveAmount: Amount::of('2944.50'),
                payableAmount: Amount::of('2944.50'),
                allowanceTotal: Amount::of('50.00'),
            ),
            dueDate: new DateTimeImmutable('2026-10-10'),
            notes: ['Marfa a fost livrată în bună stare.'],
            orderReference: 'CMD-2026-77',
            paymentMeans: [PaymentMeans::bankTransfer('RO49AAAA1B31007593840000', 'Pepiniera Verde SRL')],
            paymentTerms: 'Plata în 30 de zile de la emitere',
            allowancesCharges: [AllowanceCharge::allowance('50.00', 'Discount volum', '95', $s21)],
        );
    }

    public static function standardInvoiceWithPrepayment(): Document
    {
        $base = self::standardInvoice();

        return new Document(
            type: $base->type,
            number: 'PV-2026-000124',
            issueDate: $base->issueDate,
            currency: 'RON',
            seller: $base->seller,
            buyer: $base->buyer,
            lines: $base->lines,
            taxSubtotals: $base->taxSubtotals,
            totals: new Totals(
                lineExtensionAmount: Amount::of('2500.00'),
                taxExclusiveAmount: Amount::of('2450.00'),
                taxAmount: Amount::of('494.50'),
                taxInclusiveAmount: Amount::of('2944.50'),
                payableAmount: Amount::of('1944.52'),
                allowanceTotal: Amount::of('50.00'),
                prepaidAmount: Amount::of('1000.00'),
                payableRoundingAmount: Amount::of('0.02'),
            ),
            dueDate: $base->dueDate,
            paymentMeans: $base->paymentMeans,
            allowancesCharges: $base->allowancesCharges,
        );
    }

    public static function creditNote(): Document
    {
        $s21 = LineVat::standard('21');

        return new Document(
            type: DocumentType::CREDIT_NOTE,
            number: 'PVC-2026-000005',
            issueDate: new DateTimeImmutable('2026-09-14'),
            currency: 'RON',
            seller: self::seller(),
            buyer: self::buyer(),
            lines: [
                new Line('1', 'Buxus sempervirens 30-40 cm, ghiveci 3 l', Amount::of('10'), Amount::of('12.50'), Amount::of('125.00'), $s21),
            ],
            taxSubtotals: [
                new TaxSubtotal(VatCategory::S, Amount::of('21'), Amount::of('125.00'), Amount::of('26.25')),
            ],
            totals: new Totals(
                lineExtensionAmount: Amount::of('125.00'),
                taxExclusiveAmount: Amount::of('125.00'),
                taxAmount: Amount::of('26.25'),
                taxInclusiveAmount: Amount::of('151.25'),
                payableAmount: Amount::of('151.25'),
            ),
            dueDate: new DateTimeImmutable('2026-10-14'),
            notes: ['Retur 10 bucăți deteriorate la transport.'],
            precedingDocuments: [new PrecedingDocument('PV-2026-000123', new DateTimeImmutable('2026-09-10'))],
            paymentMeans: [PaymentMeans::bankTransfer('RO49AAAA1B31007593840000')],
        );
    }

    public static function intraEuSupplyInEur(): Document
    {
        $k = LineVat::fromCode(RomanianVatMapping::for(VatTreatment::INTRA_EU_SUPPLY));

        return new Document(
            type: DocumentType::INVOICE,
            number: 'PV-2026-000200',
            issueDate: new DateTimeImmutable('2026-09-12'),
            currency: 'EUR',
            seller: self::seller(),
            buyer: new Party(
                name: 'Blumen Müller GmbH',
                address: new Address('Hauptstraße 1', 'München', 'DE', null, '80331'),
                taxIdentifier: ForeignIdentifier::of('DE123456789', 'DE'),
                vatRegistered: true,
            ),
            lines: [
                new Line('1', "Rosa 'Peace', container 3 l", Amount::of('200'), Amount::of('4.20'), Amount::of('840.00'), $k),
            ],
            taxSubtotals: [
                new TaxSubtotal(VatCategory::K, Amount::of('0'), Amount::of('840.00'), Amount::of('0.00'), $k->exemptionCode, $k->exemptionReason),
            ],
            totals: new Totals(
                lineExtensionAmount: Amount::of('840.00'),
                taxExclusiveAmount: Amount::of('840.00'),
                taxAmount: Amount::of('0.00'),
                taxInclusiveAmount: Amount::of('840.00'),
                payableAmount: Amount::of('840.00'),
            ),
            dueDate: new DateTimeImmutable('2026-10-12'),
            taxCurrency: 'RON',
            taxTotalInTaxCurrency: Amount::of('0.00'),
            delivery: new Delivery(new DateTimeImmutable('2026-09-11'), new Address('Hauptstraße 1', 'München', 'DE', null, '80331')),
            paymentMeans: [PaymentMeans::bankTransfer('RO49AAAA1B31007593840000')],
        );
    }

    public static function reverseCharge(): Document
    {
        $ae = LineVat::fromCode(RomanianVatMapping::for(VatTreatment::REVERSE_CHARGE));

        return new Document(
            type: DocumentType::INVOICE,
            number: 'PV-2026-000301',
            issueDate: new DateTimeImmutable('2026-09-13'),
            currency: 'RON',
            seller: self::seller(),
            buyer: self::buyer(),
            lines: [
                new Line('1', 'Semințe de floarea-soarelui, sac 25 kg', Amount::of('40'), Amount::of('310.00'), Amount::of('12400.00'), $ae, unitCode: 'KGM'),
            ],
            taxSubtotals: [
                new TaxSubtotal(VatCategory::AE, Amount::of('0'), Amount::of('12400.00'), Amount::of('0.00'), $ae->exemptionCode, $ae->exemptionReason),
            ],
            totals: new Totals(
                lineExtensionAmount: Amount::of('12400.00'),
                taxExclusiveAmount: Amount::of('12400.00'),
                taxAmount: Amount::of('0.00'),
                taxInclusiveAmount: Amount::of('12400.00'),
                payableAmount: Amount::of('12400.00'),
            ),
            dueDate: new DateTimeImmutable('2026-10-13'),
            paymentMeans: [PaymentMeans::bankTransfer('RO49AAAA1B31007593840000')],
        );
    }

    public static function b2cWithCnp(): Document
    {
        return self::b2c('PV-2026-000400', Party::consumer(
            'Ion Popescu',
            new Address('Str. Lalelelor 3', 'Brașov', 'RO', 'RO-BV', '500001'),
            Cnp::of('1800101221144'),
        ));
    }

    public static function b2cWithoutCnp(): Document
    {
        return self::b2c('PV-2026-000401', Party::consumer(
            'Maria Ionescu',
            new Address('Str. Lalelelor 4', 'Brașov', 'RO', 'RO-BV', '500001'),
        ));
    }

    public static function exempt(): Document
    {
        $e = LineVat::fromCode(RomanianVatMapping::for(VatTreatment::EXEMPT, 'VATEX-EU-F', 'Regim special de scutire pentru întreprinderile mici — art. 310 Cod fiscal'));

        $seller = new Party(
            name: 'Grădinarul Mic PFA',
            address: new Address('Str. Teiului 2', 'Sibiu', 'RO', 'RO-SB', '550001'),
            taxIdentifier: Cui::of('11223342'),
            vatRegistered: false,
        );

        return new Document(
            type: DocumentType::INVOICE,
            number: 'GM-2026-17',
            issueDate: new DateTimeImmutable('2026-09-15'),
            currency: 'RON',
            seller: $seller,
            buyer: self::buyer(),
            lines: [
                new Line('1', 'Întreținere grădină, septembrie', Amount::of('1'), Amount::of('1500.00'), Amount::of('1500.00'), $e, unitCode: 'LS'),
            ],
            taxSubtotals: [
                new TaxSubtotal(VatCategory::E, Amount::of('0'), Amount::of('1500.00'), Amount::of('0.00'), $e->exemptionCode, $e->exemptionReason),
            ],
            totals: new Totals(
                lineExtensionAmount: Amount::of('1500.00'),
                taxExclusiveAmount: Amount::of('1500.00'),
                taxAmount: Amount::of('0.00'),
                taxInclusiveAmount: Amount::of('1500.00'),
                payableAmount: Amount::of('1500.00'),
            ),
            dueDate: new DateTimeImmutable('2026-09-30'),
        );
    }

    private static function b2c(string $number, Party $buyer): Document
    {
        $s21 = LineVat::standard('21');

        return new Document(
            type: DocumentType::INVOICE,
            number: $number,
            issueDate: new DateTimeImmutable('2026-09-15'),
            currency: 'RON',
            seller: self::seller(),
            buyer: $buyer,
            lines: [
                new Line('1', 'Ficus benjamina, ghiveci 21 cm', Amount::of('2'), Amount::of('89.90'), Amount::of('179.80'), $s21),
            ],
            taxSubtotals: [
                new TaxSubtotal(VatCategory::S, Amount::of('21'), Amount::of('179.80'), Amount::of('37.76')),
            ],
            totals: new Totals(
                lineExtensionAmount: Amount::of('179.80'),
                taxExclusiveAmount: Amount::of('179.80'),
                taxAmount: Amount::of('37.76'),
                taxInclusiveAmount: Amount::of('217.56'),
                payableAmount: Amount::of('217.56'),
            ),
            dueDate: new DateTimeImmutable('2026-09-15'),
            paymentMeans: [new PaymentMeans('10')],
        );
    }
}
