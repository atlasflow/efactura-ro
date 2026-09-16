<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Address;
use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\Party;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationError;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/**
 * The CIUS-RO length and cardinality limits (RO16931-rules.sch):
 * BR-RO-L020 post codes ≤ 20; BR-RO-L050 cities ≤ 50; BR-RO-L100 address
 * lines 2–3, contact fields, item names, allowance/charge reasons and
 * exemption reason text ≤ 100; BR-RO-L150 address line 1 ≤ 150;
 * BR-RO-L200 party names, references, item descriptions, payment account
 * names ≤ 200; BR-RO-L300 notes, payment terms and line notes ≤ 300;
 * BR-RO-L1000 the seller's additional legal information ≤ 1000;
 * BR-RO-A020 at most 20 notes; BR-RO-A050 at most 50 supporting
 * documents; BR-RO-A500 at most 500 preceding invoice references.
 */
final class BrRoLengthLimits implements Rule
{
    use Concerns;

    /** @var list<ValidationError> */
    private array $errors = [];

    public function check(Document $document): array
    {
        $this->errors = [];

        $this->limit($document->number, 200, 'BR-RO-L155', 'number');
        $this->limit($document->orderReference, 200, 'BR-RO-L0303', 'orderReference');
        $this->limit($document->contractReference, 200, 'BR-RO-L0302', 'contractReference');
        $this->limit($document->buyerAccountingReference, 100, 'BR-RO-L1001', 'buyerAccountingReference');
        $this->limit($document->paymentTerms, 300, 'BR-RO-L301', 'paymentTerms');

        if (count($document->notes) > 20) {
            $this->errors[] = $this->error('BR-RO-A020', 'notes', 'At most 20 notes are allowed.', ValidationSource::CIUS_RO);
        }

        foreach ($document->notes as $i => $note) {
            $this->limit($note, 300, 'BR-RO-L302', "notes[$i]");
        }

        if (count($document->attachments) > 50) {
            $this->errors[] = $this->error('BR-RO-A051', 'attachments', 'At most 50 supporting documents are allowed.', ValidationSource::CIUS_RO);
        }

        if (count($document->precedingDocuments) > 500) {
            $this->errors[] = $this->error('BR-RO-A500', 'precedingDocuments', 'At most 500 preceding invoice references are allowed.', ValidationSource::CIUS_RO);
        }

        foreach ($document->precedingDocuments as $i => $preceding) {
            $this->limit($preceding->number, 200, 'BR-RO-L156', "precedingDocuments[$i].number");
        }

        foreach ($document->attachments as $i => $attachment) {
            $this->limit($attachment->id, 200, 'BR-RO-L0308', "attachments[$i].id");
            $this->limit($attachment->description, 100, 'BR-RO-L1020', "attachments[$i].description");
            $this->limit($attachment->uri, 200, 'BR-RO-L210', "attachments[$i].uri");
            $this->limit($attachment->filename, 200, 'BR-RO-L211', "attachments[$i].filename");
        }

        $this->party($document->seller, 'seller', true);
        $this->party($document->buyer, 'buyer', false);

        if ($document->payee !== null) {
            $this->limit($document->payee->name, 200, 'BR-RO-L205', 'payee.name');
        }

        if ($document->delivery?->address !== null) {
            $this->address($document->delivery->address, 'delivery.address', 'BR-RO-L0204', 'BR-RO-L0504', 'BR-RO-L1014', 'BR-RO-L1015', 'BR-RO-L154');
            $this->limit($document->delivery->partyName, 200, 'BR-RO-L207', 'delivery.partyName');
        }

        foreach ($document->paymentMeans as $i => $means) {
            $this->limit($means->accountName, 200, 'BR-RO-L208', "paymentMeans[$i].accountName");
            $this->limit($means->text, 100, 'BR-RO-L1016', "paymentMeans[$i].text");
            $this->limit($means->paymentId, 140, 'BR-RO-L140', "paymentMeans[$i].paymentId");
        }

        foreach ($document->allowancesCharges as $i => $entry) {
            $this->limit($entry->reason, 100, $entry->isCharge ? 'BR-RO-L1018' : 'BR-RO-L1017', "allowancesCharges[$i].reason");
        }

        foreach ($document->taxSubtotals as $i => $subtotal) {
            $this->limit($subtotal->exemptionReason, 100, 'BR-RO-L1019', "taxSubtotals[$i].exemptionReason");
        }

        foreach ($document->lines as $i => $line) {
            $this->limit($line->name, 100, 'BR-RO-L1024', "lines[$i].name");
            $this->limit($line->description, 200, 'BR-RO-L212', "lines[$i].description");
            $this->limit($line->note, 300, 'BR-RO-L303', "lines[$i].note");
            $this->limit($line->buyerAccountingReference, 100, 'BR-RO-L1021', "lines[$i].buyerAccountingReference");

            foreach ($line->allowancesCharges as $j => $entry) {
                $this->limit($entry->reason, 100, $entry->isCharge ? 'BR-RO-L1023' : 'BR-RO-L1022', "lines[$i].allowancesCharges[$j].reason");
            }
        }

        return $this->errors;
    }

    private function party(Party $party, string $path, bool $isSeller): void
    {
        $this->limit($party->name, 200, $isSeller ? 'BR-RO-L201' : 'BR-RO-L203', "$path.name");
        $this->limit($party->tradingName, 200, $isSeller ? 'BR-RO-L202' : 'BR-RO-L204', "$path.tradingName");

        if ($isSeller) {
            $this->limit($party->registrationNumber, 1000, 'BR-RO-L1000', "$path.registrationNumber");
            $this->address($party->address, "$path.address", 'BR-RO-L0201', 'BR-RO-L0501', 'BR-RO-L1002', 'BR-RO-L1003', 'BR-RO-L151');
            $this->limit($party->contact?->name, 100, 'BR-RO-L1004', "$path.contact.name");
            $this->limit($party->contact?->phone, 100, 'BR-RO-L1005', "$path.contact.phone");
            $this->limit($party->contact?->email, 100, 'BR-RO-L1006', "$path.contact.email");
        } else {
            $this->address($party->address, "$path.address", 'BR-RO-L0202', 'BR-RO-L0502', 'BR-RO-L1007', 'BR-RO-L1008', 'BR-RO-L152');
            $this->limit($party->contact?->name, 100, 'BR-RO-L1009', "$path.contact.name");
            $this->limit($party->contact?->phone, 100, 'BR-RO-L1010', "$path.contact.phone");
            $this->limit($party->contact?->email, 100, 'BR-RO-L1011', "$path.contact.email");
        }
    }

    private function address(Address $address, string $path, string $postRule, string $cityRule, string $line2Rule, string $line3Rule, string $line1Rule): void
    {
        $this->limit($address->postalCode, 20, $postRule, "$path.postalCode");
        $this->limit($address->city, 50, $cityRule, "$path.city");
        $this->limit($address->line2, 100, $line2Rule, "$path.line2");
        $this->limit($address->line3, 100, $line3Rule, "$path.line3");
        $this->limit($address->line, 150, $line1Rule, "$path.line");
    }

    private function limit(?string $value, int $max, string $code, string $path): void
    {
        if ($value !== null && mb_strlen(trim($value)) > $max) {
            $this->errors[] = $this->error($code, $path, sprintf('At most %d characters are allowed, found %d.', $max, mb_strlen(trim($value))), ValidationSource::CIUS_RO);
        }
    }
}
