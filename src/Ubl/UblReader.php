<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Ubl;

use AtlasFlow\EFacturaRo\Document\Address;
use AtlasFlow\EFacturaRo\Document\AllowanceCharge;
use AtlasFlow\EFacturaRo\Document\Attachment;
use AtlasFlow\EFacturaRo\Document\Classification;
use AtlasFlow\EFacturaRo\Document\Contact;
use AtlasFlow\EFacturaRo\Document\Delivery;
use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\DocumentType;
use AtlasFlow\EFacturaRo\Document\Endpoint;
use AtlasFlow\EFacturaRo\Document\Line;
use AtlasFlow\EFacturaRo\Document\LineVat;
use AtlasFlow\EFacturaRo\Document\Party;
use AtlasFlow\EFacturaRo\Document\PaymentMeans;
use AtlasFlow\EFacturaRo\Document\Period;
use AtlasFlow\EFacturaRo\Document\PrecedingDocument;
use AtlasFlow\EFacturaRo\Document\Rasp\RaspMessage;
use AtlasFlow\EFacturaRo\Document\Rasp\RaspType;
use AtlasFlow\EFacturaRo\Document\TaxSubtotal;
use AtlasFlow\EFacturaRo\Document\Totals;
use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Support\Cnp;
use AtlasFlow\EFacturaRo\Support\Cui;
use AtlasFlow\EFacturaRo\Support\ForeignIdentifier;
use AtlasFlow\EFacturaRo\Support\TaxIdentifier;
use AtlasFlow\EFacturaRo\Vat\VatCategory;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * Reads CIUS-RO UBL back into a Document. Tolerant by design: received
 * invoices come from other people's software, so unknown elements are
 * ignored, an identifier with a bad check digit is kept as a
 * ForeignIdentifier rather than refused, and only what the Document
 * constructor itself insists on can make a read fail.
 */
final class UblReader
{
    private DOMXPath $xpath;

    public function read(string $xml): Document
    {
        $dom = $this->load($xml);
        $root = $dom->documentElement;

        if ($root === null || ! in_array($root->localName, ['Invoice', 'CreditNote'], true)) {
            throw new InvalidArgumentException('Not a UBL Invoice or CreditNote.');
        }

        $isCreditNote = $root->localName === 'CreditNote';
        $typeCode = $this->text($root, $isCreditNote ? 'cbc:CreditNoteTypeCode' : 'cbc:InvoiceTypeCode') ?? ($isCreditNote ? '381' : '380');
        $type = DocumentType::tryFrom($typeCode) ?? ($isCreditNote ? DocumentType::CREDIT_NOTE : DocumentType::INVOICE);

        $currency = $this->text($root, 'cbc:DocumentCurrencyCode') ?? 'RON';
        $taxCurrency = $this->text($root, 'cbc:TaxCurrencyCode');

        $seller = $this->party($this->element($root, 'cac:AccountingSupplierParty/cac:Party'), false);
        $buyer = $this->party($this->element($root, 'cac:AccountingCustomerParty/cac:Party'), true);

        $dueDate = $isCreditNote
            ? $this->date($root, 'cac:PaymentMeans/cbc:PaymentDueDate')
            : $this->date($root, 'cbc:DueDate');

        return new Document(
            type: $type,
            number: $this->text($root, 'cbc:ID') ?? '',
            issueDate: $this->date($root, 'cbc:IssueDate') ?? new DateTimeImmutable('1970-01-01'),
            currency: $currency,
            seller: $seller,
            buyer: $buyer,
            lines: $this->lines($root, $isCreditNote),
            taxSubtotals: $this->taxSubtotals($root, $currency),
            totals: $this->totals($root),
            dueDate: $dueDate,
            taxCurrency: $taxCurrency,
            taxTotalInTaxCurrency: $taxCurrency !== null && $taxCurrency !== $currency
                ? $this->amount($root, sprintf('cac:TaxTotal/cbc:TaxAmount[@currencyID="%s"]', $taxCurrency))
                : null,
            notes: $this->texts($root, 'cbc:Note'),
            buyerReference: $this->text($root, 'cbc:BuyerReference'),
            orderReference: $this->text($root, 'cac:OrderReference/cbc:ID'),
            contractReference: $this->text($root, 'cac:ContractDocumentReference/cbc:ID'),
            precedingDocuments: $this->precedingDocuments($root),
            payee: $this->payee($root),
            delivery: $this->delivery($root),
            period: $this->period($this->elementOrNull($root, 'cac:InvoicePeriod')),
            paymentMeans: $this->paymentMeans($root),
            paymentTerms: $this->text($root, 'cac:PaymentTerms/cbc:Note'),
            allowancesCharges: $this->allowancesCharges($root, true),
            attachments: $this->attachments($root),
            taxPointDate: $this->date($root, 'cbc:TaxPointDate'),
            buyerAccountingReference: $this->text($root, 'cbc:AccountingCost'),
            customizationId: $this->text($root, 'cbc:CustomizationID') ?? '',
            profileId: $this->text($root, 'cbc:ProfileID') ?? '',
        );
    }

    public function readRasp(string $xml): RaspMessage
    {
        $dom = $this->load($xml);
        $root = $dom->documentElement;

        if ($root === null || $root->localName !== 'RaspMessage') {
            throw new InvalidArgumentException('Not a RASP message as this package writes it.');
        }

        $get = fn (string $name): ?string => $root->getElementsByTagName($name)->item(0)?->textContent;

        return new RaspMessage(
            invoiceNumber: $get('InvoiceNumber') ?? '',
            invoiceIssueDate: new DateTimeImmutable($get('InvoiceIssueDate') ?? '1970-01-01'),
            sellerCui: Cui::of($get('SellerCui') ?? ''),
            buyerCui: Cui::of($get('BuyerCui') ?? ''),
            type: RaspType::from($get('Type') ?? RaspType::COMMENT->value),
            text: $get('Text'),
        );
    }

    private function load(string $xml): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            $message = $errors === [] ? 'malformed XML' : trim($errors[0]->message);

            throw new InvalidArgumentException('Cannot parse the document: '.$message);
        }

        $this->xpath = new DOMXPath($dom);
        $this->xpath->registerNamespace('cac', Namespaces::CAC);
        $this->xpath->registerNamespace('cbc', Namespaces::CBC);

        return $dom;
    }

    private function party(DOMElement $node, bool $isBuyer): Party
    {
        $vatId = $this->text($node, 'cac:PartyTaxScheme[cac:TaxScheme/cbc:ID="VAT"]/cbc:CompanyID');
        $taxRegistration = $this->text($node, 'cac:PartyTaxScheme[not(cac:TaxScheme/cbc:ID="VAT")]/cbc:CompanyID');
        $legalId = $this->text($node, 'cac:PartyLegalEntity/cbc:CompanyID');
        $address = $this->address($this->element($node, 'cac:PostalAddress'));

        $vatRegistered = $vatId !== null;
        $raw = $vatId ?? $taxRegistration ?? $legalId;
        $isConsumer = false;
        $identifier = null;

        if ($raw !== null) {
            $identifier = $this->taxIdentifier($raw, $address->country);

            if ($isBuyer && ! $vatRegistered && ($identifier instanceof Cnp || $raw === Cnp::PLACEHOLDER)) {
                $isConsumer = true;
                $identifier = $identifier instanceof Cnp ? $identifier : null;
            }
        }

        $contactNode = $this->elementOrNull($node, 'cac:Contact');
        $contact = $contactNode === null ? null : new Contact(
            $this->text($contactNode, 'cbc:Name'),
            $this->text($contactNode, 'cbc:ElectronicMail'),
            $this->text($contactNode, 'cbc:Telephone'),
        );

        $endpointNode = $this->elementOrNull($node, 'cbc:EndpointID');
        $endpoint = $endpointNode === null ? null : new Endpoint($endpointNode->textContent, $endpointNode->getAttribute('schemeID') ?: 'EM');

        return new Party(
            name: $this->text($node, 'cac:PartyLegalEntity/cbc:RegistrationName') ?? $this->text($node, 'cac:PartyName/cbc:Name') ?? '',
            address: $address,
            taxIdentifier: $identifier,
            vatRegistered: $vatRegistered,
            registrationNumber: $this->text($node, 'cac:PartyLegalEntity/cbc:CompanyLegalForm'),
            tradingName: $this->text($node, 'cac:PartyName/cbc:Name'),
            contact: $contact,
            endpoint: $endpoint,
            identifier: $this->text($node, 'cac:PartyIdentification/cbc:ID'),
            isConsumer: $isConsumer,
        );
    }

    private function taxIdentifier(string $raw, string $country): TaxIdentifier
    {
        $value = trim($raw);
        $prefix = strtoupper(substr($value, 0, 2));
        $issuer = preg_match('/^[A-Z]{2}$/', $prefix) ? $prefix : $country;

        if ($issuer === 'RO') {
            $digits = preg_replace('/^RO/i', '', $value) ?? $value;

            if (Cui::isValid($digits)) {
                return Cui::of($digits);
            }

            if (Cnp::isValid($digits)) {
                return Cnp::of($digits);
            }
        }

        return ForeignIdentifier::of($value, preg_match('/^[A-Z]{2}$/', $issuer) ? $issuer : 'RO');
    }

    private function payee(DOMElement $root): ?Party
    {
        $node = $this->elementOrNull($root, 'cac:PayeeParty');

        if ($node === null) {
            return null;
        }

        $legalId = $this->text($node, 'cac:PartyLegalEntity/cbc:CompanyID');

        return new Party(
            name: $this->text($node, 'cac:PartyName/cbc:Name') ?? '',
            address: new Address('', '', 'RO'),
            taxIdentifier: $legalId === null ? null : $this->taxIdentifier($legalId, 'RO'),
            identifier: $this->text($node, 'cac:PartyIdentification/cbc:ID'),
        );
    }

    private function address(DOMElement $node): Address
    {
        return new Address(
            line: $this->text($node, 'cbc:StreetName') ?? '',
            city: $this->text($node, 'cbc:CityName') ?? '',
            country: $this->text($node, 'cac:Country/cbc:IdentificationCode') ?? 'RO',
            countySubdivision: $this->text($node, 'cbc:CountrySubentity'),
            postalCode: $this->text($node, 'cbc:PostalZone'),
            line2: $this->text($node, 'cbc:AdditionalStreetName'),
            line3: $this->text($node, 'cac:AddressLine/cbc:Line'),
        );
    }

    private function delivery(DOMElement $root): ?Delivery
    {
        $node = $this->elementOrNull($root, 'cac:Delivery');

        if ($node === null) {
            return null;
        }

        $addressNode = $this->elementOrNull($node, 'cac:DeliveryLocation/cac:Address');

        return new Delivery(
            actualDate: $this->date($node, 'cbc:ActualDeliveryDate'),
            address: $addressNode === null ? null : $this->address($addressNode),
            partyName: $this->text($node, 'cac:DeliveryParty/cac:PartyName/cbc:Name'),
            locationId: $this->text($node, 'cac:DeliveryLocation/cbc:ID'),
        );
    }

    private function period(?DOMElement $node): ?Period
    {
        if ($node === null) {
            return null;
        }

        $start = $this->date($node, 'cbc:StartDate');
        $end = $this->date($node, 'cbc:EndDate');
        $code = $this->text($node, 'cbc:DescriptionCode');

        return $start === null && $end === null && $code === null ? null : new Period($start, $end, $code);
    }

    /** @return list<PaymentMeans> */
    private function paymentMeans(DOMElement $root): array
    {
        $means = [];

        foreach ($this->elements($root, 'cac:PaymentMeans') as $node) {
            $codeNode = $this->elementOrNull($node, 'cbc:PaymentMeansCode');

            $means[] = new PaymentMeans(
                code: $codeNode === null ? '31' : trim($codeNode->textContent),
                iban: $this->text($node, 'cac:PayeeFinancialAccount/cbc:ID'),
                accountName: $this->text($node, 'cac:PayeeFinancialAccount/cbc:Name'),
                bic: $this->text($node, 'cac:PayeeFinancialAccount/cac:FinancialInstitutionBranch/cbc:ID'),
                paymentId: $this->text($node, 'cbc:PaymentID'),
                text: $codeNode?->getAttribute('name') ?: null,
            );
        }

        return $means;
    }

    /** @return list<PrecedingDocument> */
    private function precedingDocuments(DOMElement $root): array
    {
        $documents = [];

        foreach ($this->elements($root, 'cac:BillingReference/cac:InvoiceDocumentReference') as $node) {
            $documents[] = new PrecedingDocument($this->text($node, 'cbc:ID') ?? '', $this->date($node, 'cbc:IssueDate'));
        }

        return $documents;
    }

    /** @return list<Attachment> */
    private function attachments(DOMElement $root): array
    {
        $attachments = [];

        foreach ($this->elements($root, 'cac:AdditionalDocumentReference') as $node) {
            $binary = $this->elementOrNull($node, 'cac:Attachment/cbc:EmbeddedDocumentBinaryObject');

            $attachments[] = new Attachment(
                id: $this->text($node, 'cbc:ID') ?? '',
                description: $this->text($node, 'cbc:DocumentDescription'),
                uri: $this->text($node, 'cac:Attachment/cac:ExternalReference/cbc:URI'),
                content: $binary === null ? null : (base64_decode(trim($binary->textContent), true) ?: null),
                mimeType: $binary?->getAttribute('mimeCode') ?: null,
                filename: $binary?->getAttribute('filename') ?: null,
            );
        }

        return $attachments;
    }

    /** @return list<AllowanceCharge> */
    private function allowancesCharges(DOMElement $parent, bool $documentLevel): array
    {
        $entries = [];

        foreach ($this->elements($parent, 'cac:AllowanceCharge') as $node) {
            $vatNode = $documentLevel ? $this->elementOrNull($node, 'cac:TaxCategory') : null;

            $entries[] = new AllowanceCharge(
                isCharge: ($this->text($node, 'cbc:ChargeIndicator') ?? 'false') === 'true',
                amount: $this->amount($node, 'cbc:Amount') ?? Amount::zero(),
                reason: $this->text($node, 'cbc:AllowanceChargeReason'),
                reasonCode: $this->text($node, 'cbc:AllowanceChargeReasonCode'),
                baseAmount: $this->amount($node, 'cbc:BaseAmount'),
                percentage: $this->amount($node, 'cbc:MultiplierFactorNumeric'),
                vat: $vatNode === null ? null : $this->lineVat($vatNode),
            );
        }

        return $entries;
    }

    private function lineVat(DOMElement $node): LineVat
    {
        return new LineVat(
            category: VatCategory::tryFrom(trim($this->text($node, 'cbc:ID') ?? 'S')) ?? VatCategory::S,
            rate: $this->amount($node, 'cbc:Percent'),
            exemptionCode: $this->text($node, 'cbc:TaxExemptionReasonCode'),
            exemptionReason: $this->text($node, 'cbc:TaxExemptionReason'),
        );
    }

    /** @return list<TaxSubtotal> */
    private function taxSubtotals(DOMElement $root, string $currency): array
    {
        $subtotals = [];

        foreach ($this->elements($root, 'cac:TaxTotal/cac:TaxSubtotal') as $node) {
            $category = $this->element($node, 'cac:TaxCategory');

            $subtotals[] = new TaxSubtotal(
                category: VatCategory::tryFrom(trim($this->text($category, 'cbc:ID') ?? 'S')) ?? VatCategory::S,
                rate: $this->amount($category, 'cbc:Percent'),
                taxableAmount: $this->amount($node, 'cbc:TaxableAmount') ?? Amount::zero(),
                taxAmount: $this->amount($node, 'cbc:TaxAmount') ?? Amount::zero(),
                exemptionCode: $this->text($category, 'cbc:TaxExemptionReasonCode'),
                exemptionReason: $this->text($category, 'cbc:TaxExemptionReason'),
            );
        }

        return $subtotals;
    }

    private function totals(DOMElement $root): Totals
    {
        $node = $this->element($root, 'cac:LegalMonetaryTotal');
        $currency = $this->text($root, 'cbc:DocumentCurrencyCode') ?? 'RON';

        return new Totals(
            lineExtensionAmount: $this->amount($node, 'cbc:LineExtensionAmount') ?? Amount::zero(),
            taxExclusiveAmount: $this->amount($node, 'cbc:TaxExclusiveAmount') ?? Amount::zero(),
            taxAmount: $this->amount($root, sprintf('cac:TaxTotal/cbc:TaxAmount[@currencyID="%s"]', $currency))
                ?? $this->amount($root, 'cac:TaxTotal[1]/cbc:TaxAmount')
                ?? Amount::zero(),
            taxInclusiveAmount: $this->amount($node, 'cbc:TaxInclusiveAmount') ?? Amount::zero(),
            payableAmount: $this->amount($node, 'cbc:PayableAmount') ?? Amount::zero(),
            allowanceTotal: $this->amount($node, 'cbc:AllowanceTotalAmount'),
            chargeTotal: $this->amount($node, 'cbc:ChargeTotalAmount'),
            prepaidAmount: $this->amount($node, 'cbc:PrepaidAmount'),
            payableRoundingAmount: $this->amount($node, 'cbc:PayableRoundingAmount'),
        );
    }

    /** @return list<Line> */
    private function lines(DOMElement $root, bool $isCreditNote): array
    {
        $lines = [];

        foreach ($this->elements($root, $isCreditNote ? 'cac:CreditNoteLine' : 'cac:InvoiceLine') as $node) {
            $quantityNode = $this->element($node, $isCreditNote ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity');
            $item = $this->element($node, 'cac:Item');
            $classificationNode = $this->elementOrNull($item, 'cac:CommodityClassification/cbc:ItemClassificationCode');
            $vatNode = $this->elementOrNull($item, 'cac:ClassifiedTaxCategory');

            $lines[] = new Line(
                id: $this->text($node, 'cbc:ID') ?? '',
                name: $this->text($item, 'cbc:Name') ?? '',
                quantity: Amount::of(trim($quantityNode->textContent)),
                unitPrice: $this->amount($node, 'cac:Price/cbc:PriceAmount') ?? Amount::zero(),
                netAmount: $this->amount($node, 'cbc:LineExtensionAmount') ?? Amount::zero(),
                vat: $vatNode === null ? new LineVat(VatCategory::S, Amount::of('0')) : $this->lineVat($vatNode),
                unitCode: $quantityNode->getAttribute('unitCode') ?: 'H87',
                description: $this->text($item, 'cbc:Description'),
                priceBaseQuantity: $this->amount($node, 'cac:Price/cbc:BaseQuantity'),
                classification: $classificationNode === null ? null : new Classification($classificationNode->textContent, $classificationNode->getAttribute('listID')),
                sellerItemId: $this->text($item, 'cac:SellersItemIdentification/cbc:ID'),
                note: $this->text($node, 'cbc:Note'),
                period: $this->period($this->elementOrNull($node, 'cac:InvoicePeriod')),
                buyerAccountingReference: $this->text($node, 'cbc:AccountingCost'),
                allowancesCharges: $this->allowancesCharges($node, false),
            );
        }

        return $lines;
    }

    private function element(DOMElement $context, string $path): DOMElement
    {
        $node = $this->elementOrNull($context, $path);

        if ($node === null) {
            throw new InvalidArgumentException(sprintf('The document has no %s.', $path));
        }

        return $node;
    }

    private function elementOrNull(DOMElement $context, string $path): ?DOMElement
    {
        $node = $this->xpath->query($path, $context)?->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    /** @return list<DOMElement> */
    private function elements(DOMElement $context, string $path): array
    {
        $nodes = [];

        foreach ($this->xpath->query($path, $context) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function text(DOMElement $context, string $path): ?string
    {
        $node = $this->elementOrNull($context, $path);

        if ($node === null) {
            return null;
        }

        $value = trim($node->textContent);

        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private function texts(DOMElement $context, string $path): array
    {
        return array_values(array_filter(array_map(fn (DOMElement $n) => trim($n->textContent), $this->elements($context, $path)), fn ($v) => $v !== ''));
    }

    private function amount(DOMElement $context, string $path): ?Amount
    {
        $value = $this->text($context, $path);

        return $value === null ? null : Amount::of($value);
    }

    private function date(DOMElement $context, string $path): ?DateTimeImmutable
    {
        $value = $this->text($context, $path);

        return $value === null ? null : new DateTimeImmutable($value);
    }
}
