<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Ubl;

use AtlasFlow\EFacturaRo\Document\Address;
use AtlasFlow\EFacturaRo\Document\AllowanceCharge;
use AtlasFlow\EFacturaRo\Document\Attachment;
use AtlasFlow\EFacturaRo\Document\Delivery;
use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\Line;
use AtlasFlow\EFacturaRo\Document\LineVat;
use AtlasFlow\EFacturaRo\Document\Party;
use AtlasFlow\EFacturaRo\Document\PaymentMeans;
use AtlasFlow\EFacturaRo\Document\Period;
use AtlasFlow\EFacturaRo\Document\Rasp\RaspMessage;
use AtlasFlow\EFacturaRo\Document\TaxSubtotal;
use AtlasFlow\EFacturaRo\Support\Amount;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;

/**
 * Writes a Document as CIUS-RO UBL 2.1 — an Invoice for every type but 381,
 * a CreditNote for 381. Element order follows the OASIS schema exactly, the
 * output is deterministic (same document, same bytes), and nothing is
 * computed: every number on the wire is the Amount the caller supplied.
 *
 * How parties are identified (verified against ANAF's validator, 2026-09-16):
 * - VAT-registered: PartyTaxScheme/CompanyID = country prefix + identifier,
 *   TaxScheme/ID = VAT (BT-31 / BT-48). PartyLegalEntity/CompanyID carries
 *   the bare identifier (BT-30 / BT-47).
 * - not registered: PartyTaxScheme/CompanyID = bare identifier with
 *   TaxScheme/ID = TAX (BT-32, which BR-RO-065 and BR-S-02 demand for the
 *   seller), plus PartyLegalEntity/CompanyID (BT-30, demanded by BR-CO-26).
 * - consumer: no PartyTaxScheme; PartyLegalEntity/CompanyID = CNP or the
 *   placeholder 0000000000000.
 * The seller's trade register number goes in PartyLegalEntity/CompanyLegalForm
 * (BT-33); the buyer has no such element in EN 16931 (UBL-CR-244 refuses it),
 * so the buyer's registration number is not written.
 */
final class UblWriter
{
    private const string UBL_VERSION = '2.1';

    /** BT-32 scheme for a party that is not registered for VAT. */
    public const string NON_VAT_TAX_SCHEME = 'TAX';

    private DOMDocument $dom;

    private string $currency;

    public function write(Document $document): string
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
        $this->currency = $document->currency;

        $rootNs = $document->isCreditNote() ? Namespaces::CREDIT_NOTE : Namespaces::INVOICE;
        $root = $this->dom->createElementNS($rootNs, $document->type->ublRootElement());
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', Namespaces::CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', Namespaces::CBC);
        $this->dom->appendChild($root);

        $this->cbc($root, 'UBLVersionID', self::UBL_VERSION);
        $this->cbc($root, 'CustomizationID', $document->customizationId);
        $this->cbc($root, 'ProfileID', $document->profileId);
        $this->cbc($root, 'ID', $document->number);
        $this->cbc($root, 'IssueDate', $this->date($document->issueDate));

        if ($document->isCreditNote()) {
            $this->cbcIf($root, 'TaxPointDate', $document->taxPointDate === null ? null : $this->date($document->taxPointDate));
            $this->cbc($root, 'CreditNoteTypeCode', $document->type->value);
        } else {
            $this->cbcIf($root, 'DueDate', $document->dueDate === null ? null : $this->date($document->dueDate));
            $this->cbc($root, 'InvoiceTypeCode', $document->type->value);
        }

        foreach ($document->notes as $note) {
            $this->cbc($root, 'Note', $note);
        }

        if (! $document->isCreditNote()) {
            $this->cbcIf($root, 'TaxPointDate', $document->taxPointDate === null ? null : $this->date($document->taxPointDate));
        }

        $this->cbc($root, 'DocumentCurrencyCode', $document->currency);
        $this->cbcIf($root, 'TaxCurrencyCode', $document->taxCurrency);
        $this->cbcIf($root, 'AccountingCost', $document->buyerAccountingReference);
        $this->cbcIf($root, 'BuyerReference', $document->buyerReference);

        if ($document->period !== null) {
            $this->period($root, 'InvoicePeriod', $document->period);
        }

        if ($document->orderReference !== null) {
            $ref = $this->cac($root, 'OrderReference');
            $this->cbc($ref, 'ID', $document->orderReference);
        }

        foreach ($document->precedingDocuments as $preceding) {
            $billing = $this->cac($root, 'BillingReference');
            $ref = $this->cac($billing, 'InvoiceDocumentReference');
            $this->cbc($ref, 'ID', $preceding->number);
            $this->cbcIf($ref, 'IssueDate', $preceding->issueDate === null ? null : $this->date($preceding->issueDate));
        }

        if ($document->contractReference !== null) {
            $ref = $this->cac($root, 'ContractDocumentReference');
            $this->cbc($ref, 'ID', $document->contractReference);
        }

        foreach ($document->attachments as $attachment) {
            $this->attachment($root, $attachment);
        }

        $this->party($this->cac($root, 'AccountingSupplierParty'), $document->seller, true);
        $this->party($this->cac($root, 'AccountingCustomerParty'), $document->buyer, false);

        if ($document->payee !== null) {
            $this->payee($this->cac($root, 'PayeeParty'), $document->payee);
        }

        if ($document->delivery !== null) {
            $this->delivery($root, $document->delivery);
        }

        foreach ($document->paymentMeans as $means) {
            $this->paymentMeans($root, $means, $document->isCreditNote() ? $document->dueDate : null);
        }

        if ($document->paymentTerms !== null) {
            $terms = $this->cac($root, 'PaymentTerms');
            $this->cbc($terms, 'Note', $document->paymentTerms);
        }

        foreach ($document->allowancesCharges as $entry) {
            $this->allowanceCharge($root, $entry, true);
        }

        $this->taxTotals($root, $document);
        $this->monetaryTotal($root, $document);

        foreach ($document->lines as $line) {
            $this->line($root, $line, $document->isCreditNote());
        }

        return (string) $this->dom->saveXML();
    }

    /**
     * The buyer → seller message ANAF transports as standard RASP. The schema
     * MF publishes is not reachable from here; this shape follows the fields
     * the SPV form exposes and must be checked against the XSD before a RASP
     * upload is attempted (spec, open questions).
     */
    public function writeRasp(RaspMessage $message): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('RaspMessage');
        $dom->appendChild($root);

        $root->appendChild($dom->createElement('InvoiceNumber', $message->invoiceNumber));
        $root->appendChild($dom->createElement('InvoiceIssueDate', $message->invoiceIssueDate->format('Y-m-d')));
        $root->appendChild($dom->createElement('SellerCui', $message->sellerCui->digits()));
        $root->appendChild($dom->createElement('BuyerCui', $message->buyerCui->digits()));
        $root->appendChild($dom->createElement('Type', $message->type->value));

        if ($message->text !== null) {
            $root->appendChild($dom->createElement('Text', $message->text));
        }

        return (string) $dom->saveXML();
    }

    private function party(DOMElement $wrapper, Party $party, bool $isSeller): void
    {
        $node = $this->cac($wrapper, 'Party');

        if ($party->endpoint !== null) {
            $endpoint = $this->cbc($node, 'EndpointID', $party->endpoint->value);
            $endpoint->setAttribute('schemeID', $party->endpoint->schemeId);
        }

        if ($party->identifier !== null) {
            $id = $this->cac($node, 'PartyIdentification');
            $this->cbc($id, 'ID', $party->identifier);
        }

        if ($party->tradingName !== null) {
            $name = $this->cac($node, 'PartyName');
            $this->cbc($name, 'Name', $party->tradingName);
        }

        $this->address($this->cac($node, 'PostalAddress'), $party->address);

        if ($party->vatIdentifier() !== null) {
            $scheme = $this->cac($node, 'PartyTaxScheme');
            $this->cbc($scheme, 'CompanyID', $party->vatIdentifier());
            $this->cbc($this->cac($scheme, 'TaxScheme'), 'ID', 'VAT');
        } elseif (! $party->isConsumer && $party->taxIdentifier !== null) {
            $scheme = $this->cac($node, 'PartyTaxScheme');
            $this->cbc($scheme, 'CompanyID', $party->taxIdentifier->value());
            $this->cbc($this->cac($scheme, 'TaxScheme'), 'ID', self::NON_VAT_TAX_SCHEME);
        }

        $legal = $this->cac($node, 'PartyLegalEntity');
        $this->cbc($legal, 'RegistrationName', $party->name);

        $legalId = $party->isConsumer ? $party->legalRegistrationIdentifier() : $party->taxIdentifier?->value();
        $this->cbcIf($legal, 'CompanyID', $legalId);

        if ($isSeller) {
            $this->cbcIf($legal, 'CompanyLegalForm', $party->registrationNumber);
        }

        if ($party->contact !== null && ! $party->contact->isEmpty()) {
            $contact = $this->cac($node, 'Contact');
            $this->cbcIf($contact, 'Name', $party->contact->name);
            $this->cbcIf($contact, 'Telephone', $party->contact->phone);
            $this->cbcIf($contact, 'ElectronicMail', $party->contact->email);
        }
    }

    private function payee(DOMElement $node, Party $payee): void
    {
        if ($payee->identifier !== null) {
            $id = $this->cac($node, 'PartyIdentification');
            $this->cbc($id, 'ID', $payee->identifier);
        }

        $name = $this->cac($node, 'PartyName');
        $this->cbc($name, 'Name', $payee->name);

        if ($payee->taxIdentifier !== null) {
            $legal = $this->cac($node, 'PartyLegalEntity');
            $this->cbc($legal, 'CompanyID', $payee->taxIdentifier->value());
        }
    }

    private function address(DOMElement $node, Address $address): void
    {
        $this->cbc($node, 'StreetName', $address->line);
        $this->cbcIf($node, 'AdditionalStreetName', $address->line2);
        $this->cbc($node, 'CityName', $address->city);
        $this->cbcIf($node, 'PostalZone', $address->postalCode);
        $this->cbcIf($node, 'CountrySubentity', $address->countySubdivision);

        if ($address->line3 !== null) {
            $line = $this->cac($node, 'AddressLine');
            $this->cbc($line, 'Line', $address->line3);
        }

        $this->cbc($this->cac($node, 'Country'), 'IdentificationCode', $address->country);
    }

    private function delivery(DOMElement $root, Delivery $delivery): void
    {
        $node = $this->cac($root, 'Delivery');
        $this->cbcIf($node, 'ActualDeliveryDate', $delivery->actualDate === null ? null : $this->date($delivery->actualDate));

        if ($delivery->address !== null || $delivery->locationId !== null) {
            $location = $this->cac($node, 'DeliveryLocation');
            $this->cbcIf($location, 'ID', $delivery->locationId);

            if ($delivery->address !== null) {
                $this->address($this->cac($location, 'Address'), $delivery->address);
            }
        }

        if ($delivery->partyName !== null) {
            $party = $this->cac($node, 'DeliveryParty');
            $this->cbc($this->cac($party, 'PartyName'), 'Name', $delivery->partyName);
        }
    }

    private function paymentMeans(DOMElement $root, PaymentMeans $means, ?DateTimeImmutable $creditNoteDueDate): void
    {
        $node = $this->cac($root, 'PaymentMeans');
        $code = $this->cbc($node, 'PaymentMeansCode', $means->code);

        if ($means->text !== null) {
            $code->setAttribute('name', $means->text);
        }

        $this->cbcIf($node, 'PaymentDueDate', $creditNoteDueDate === null ? null : $this->date($creditNoteDueDate));
        $this->cbcIf($node, 'PaymentID', $means->paymentId);

        if ($means->iban !== null) {
            $account = $this->cac($node, 'PayeeFinancialAccount');
            $this->cbc($account, 'ID', $means->iban);
            $this->cbcIf($account, 'Name', $means->accountName);

            if ($means->bic !== null) {
                $this->cbc($this->cac($account, 'FinancialInstitutionBranch'), 'ID', $means->bic);
            }
        }
    }

    private function allowanceCharge(DOMElement $parent, AllowanceCharge $entry, bool $documentLevel): void
    {
        $node = $this->cac($parent, 'AllowanceCharge');
        $this->cbc($node, 'ChargeIndicator', $entry->isCharge ? 'true' : 'false');
        $this->cbcIf($node, 'AllowanceChargeReasonCode', $entry->reasonCode);
        $this->cbcIf($node, 'AllowanceChargeReason', $entry->reason);
        $this->cbcIf($node, 'MultiplierFactorNumeric', $entry->percentage?->toString());
        $this->amount($node, 'Amount', $entry->amount);
        $this->amountIf($node, 'BaseAmount', $entry->baseAmount);

        if ($documentLevel && $entry->vat !== null) {
            $this->taxCategory($node, 'TaxCategory', $entry->vat, false);
        }
    }

    private function taxCategory(DOMElement $parent, string $name, LineVat $vat, bool $withExemption): void
    {
        $node = $this->cac($parent, $name);
        $this->cbc($node, 'ID', $vat->category->value);
        $this->cbcIf($node, 'Percent', $vat->rate?->toString());

        if ($withExemption) {
            $this->cbcIf($node, 'TaxExemptionReasonCode', $vat->exemptionCode);
            $this->cbcIf($node, 'TaxExemptionReason', $vat->exemptionReason);
        }

        $this->cbc($this->cac($node, 'TaxScheme'), 'ID', 'VAT');
    }

    private function taxTotals(DOMElement $root, Document $document): void
    {
        $total = $this->cac($root, 'TaxTotal');
        $this->amount($total, 'TaxAmount', $document->totals->taxAmount);

        foreach ($document->taxSubtotals as $subtotal) {
            $this->taxSubtotal($total, $subtotal);
        }

        if ($document->taxCurrency !== null && $document->taxCurrency !== $document->currency && $document->taxTotalInTaxCurrency !== null) {
            $restated = $this->cac($root, 'TaxTotal');
            $this->amount($restated, 'TaxAmount', $document->taxTotalInTaxCurrency, $document->taxCurrency);
        }
    }

    private function taxSubtotal(DOMElement $total, TaxSubtotal $subtotal): void
    {
        $node = $this->cac($total, 'TaxSubtotal');
        $this->amount($node, 'TaxableAmount', $subtotal->taxableAmount);
        $this->amount($node, 'TaxAmount', $subtotal->taxAmount);

        $category = $this->cac($node, 'TaxCategory');
        $this->cbc($category, 'ID', $subtotal->category->value);
        $this->cbcIf($category, 'Percent', $subtotal->rate?->toString());
        $this->cbcIf($category, 'TaxExemptionReasonCode', $subtotal->exemptionCode);
        $this->cbcIf($category, 'TaxExemptionReason', $subtotal->exemptionReason);
        $this->cbc($this->cac($category, 'TaxScheme'), 'ID', 'VAT');
    }

    private function monetaryTotal(DOMElement $root, Document $document): void
    {
        $totals = $document->totals;
        $node = $this->cac($root, 'LegalMonetaryTotal');
        $this->amount($node, 'LineExtensionAmount', $totals->lineExtensionAmount);
        $this->amount($node, 'TaxExclusiveAmount', $totals->taxExclusiveAmount);
        $this->amount($node, 'TaxInclusiveAmount', $totals->taxInclusiveAmount);
        $this->amountIf($node, 'AllowanceTotalAmount', $totals->allowanceTotal);
        $this->amountIf($node, 'ChargeTotalAmount', $totals->chargeTotal);
        $this->amountIf($node, 'PrepaidAmount', $totals->prepaidAmount);
        $this->amountIf($node, 'PayableRoundingAmount', $totals->payableRoundingAmount);
        $this->amount($node, 'PayableAmount', $totals->payableAmount);
    }

    private function line(DOMElement $root, Line $line, bool $creditNote): void
    {
        $node = $this->cac($root, $creditNote ? 'CreditNoteLine' : 'InvoiceLine');
        $this->cbc($node, 'ID', $line->id);
        $this->cbcIf($node, 'Note', $line->note);

        $quantity = $this->cbc($node, $creditNote ? 'CreditedQuantity' : 'InvoicedQuantity', $line->quantity->toString());
        $quantity->setAttribute('unitCode', $line->unitCode);

        $this->amount($node, 'LineExtensionAmount', $line->netAmount);
        $this->cbcIf($node, 'AccountingCost', $line->buyerAccountingReference);

        if ($line->period !== null) {
            $this->period($node, 'InvoicePeriod', $line->period);
        }

        foreach ($line->allowancesCharges as $entry) {
            $this->allowanceCharge($node, $entry, false);
        }

        $item = $this->cac($node, 'Item');
        $this->cbcIf($item, 'Description', $line->description);
        $this->cbc($item, 'Name', $line->name);

        if ($line->sellerItemId !== null) {
            $this->cbc($this->cac($item, 'SellersItemIdentification'), 'ID', $line->sellerItemId);
        }

        if ($line->classification !== null) {
            $classification = $this->cac($item, 'CommodityClassification');
            $code = $this->cbc($classification, 'ItemClassificationCode', $line->classification->code);
            $code->setAttribute('listID', $line->classification->listId);
        }

        $this->taxCategory($item, 'ClassifiedTaxCategory', $line->vat, false);

        $price = $this->cac($node, 'Price');
        $this->amount($price, 'PriceAmount', $line->unitPrice);

        if ($line->priceBaseQuantity !== null) {
            $base = $this->cbc($price, 'BaseQuantity', $line->priceBaseQuantity->toString());
            $base->setAttribute('unitCode', $line->unitCode);
        }
    }

    private function period(DOMElement $parent, string $name, Period $period): void
    {
        $node = $this->cac($parent, $name);
        $this->cbcIf($node, 'StartDate', $period->start === null ? null : $this->date($period->start));
        $this->cbcIf($node, 'EndDate', $period->end === null ? null : $this->date($period->end));
        $this->cbcIf($node, 'DescriptionCode', $period->descriptionCode);
    }

    private function attachment(DOMElement $root, Attachment $attachment): void
    {
        $node = $this->cac($root, 'AdditionalDocumentReference');
        $this->cbc($node, 'ID', $attachment->id);
        $this->cbcIf($node, 'DocumentDescription', $attachment->description);

        if ($attachment->content === null && $attachment->uri === null) {
            return;
        }

        $wrapper = $this->cac($node, 'Attachment');

        if ($attachment->content !== null) {
            $binary = $this->cbc($wrapper, 'EmbeddedDocumentBinaryObject', base64_encode($attachment->content));
            $binary->setAttribute('mimeCode', $attachment->mimeType ?? 'application/octet-stream');
            $binary->setAttribute('filename', $attachment->filename ?? $attachment->id);
        }

        if ($attachment->uri !== null) {
            $this->cbc($this->cac($wrapper, 'ExternalReference'), 'URI', $attachment->uri);
        }
    }

    private function amount(DOMElement $parent, string $name, Amount $amount, ?string $currency = null): DOMElement
    {
        $node = $this->cbc($parent, $name, $amount->toString());
        $node->setAttribute('currencyID', $currency ?? $this->currency);

        return $node;
    }

    private function amountIf(DOMElement $parent, string $name, ?Amount $amount): void
    {
        if ($amount !== null) {
            $this->amount($parent, $name, $amount);
        }
    }

    private function cac(DOMElement $parent, string $name): DOMElement
    {
        $node = $this->dom->createElementNS(Namespaces::CAC, 'cac:'.$name);
        $parent->appendChild($node);

        return $node;
    }

    private function cbc(DOMElement $parent, string $name, string $value): DOMElement
    {
        $node = $this->dom->createElementNS(Namespaces::CBC, 'cbc:'.$name);
        $node->appendChild($this->dom->createTextNode($value));
        $parent->appendChild($node);

        return $node;
    }

    private function cbcIf(DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->cbc($parent, $name, $value);
        }
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d');
    }
}
