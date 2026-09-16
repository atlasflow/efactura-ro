<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

use AtlasFlow\EFacturaRo\Support\Amount;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * An EN 16931 invoice or credit note as CIUS-RO shapes it. Immutable; every
 * amount arrives as an Amount the caller computed. Construction enforces
 * only what makes a document meaningless without it — a credit note with
 * nothing to credit, a foreign-currency document with no RON tax currency,
 * no lines at all; everything else is the validator's job.
 */
final readonly class Document
{
    public const string CIUS_RO_CUSTOMIZATION_ID = 'urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1';

    public const string PROFILE_ID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /**
     * @param  list<Line>  $lines
     * @param  list<TaxSubtotal>  $taxSubtotals
     * @param  list<PrecedingDocument>  $precedingDocuments
     * @param  list<PaymentMeans>  $paymentMeans
     * @param  list<AllowanceCharge>  $allowancesCharges
     * @param  list<string>  $notes
     * @param  list<Attachment>  $attachments
     */
    public function __construct(
        public DocumentType $type,
        public string $number,
        public DateTimeImmutable $issueDate,
        public string $currency,
        public Party $seller,
        public Party $buyer,
        public array $lines,
        public array $taxSubtotals,
        public Totals $totals,
        public ?DateTimeImmutable $dueDate = null,
        public ?string $taxCurrency = null,
        public ?Amount $taxTotalInTaxCurrency = null,
        public array $notes = [],
        public ?string $buyerReference = null,
        public ?string $orderReference = null,
        public ?string $contractReference = null,
        public array $precedingDocuments = [],
        public ?Party $payee = null,
        public ?Delivery $delivery = null,
        public ?Period $period = null,
        public array $paymentMeans = [],
        public ?string $paymentTerms = null,
        public array $allowancesCharges = [],
        public array $attachments = [],
        public ?DateTimeImmutable $taxPointDate = null,
        public ?string $buyerAccountingReference = null,
        public string $customizationId = self::CIUS_RO_CUSTOMIZATION_ID,
        public string $profileId = self::PROFILE_ID,
    ) {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException(sprintf('"%s" is not an ISO 4217 currency code.', $currency));
        }

        if ($taxCurrency !== null && ! preg_match('/^[A-Z]{3}$/', $taxCurrency)) {
            throw new InvalidArgumentException(sprintf('"%s" is not an ISO 4217 currency code.', $taxCurrency));
        }

        if ($currency !== 'RON' && $taxCurrency !== 'RON') {
            throw new InvalidArgumentException('A document not in RON must carry taxCurrency "RON" and the VAT total restated in RON (BR-RO-030, BR-53).');
        }

        if ($taxCurrency !== null && $taxCurrency !== $currency && $taxTotalInTaxCurrency === null) {
            throw new InvalidArgumentException('When the tax currency differs from the document currency the VAT total in the tax currency (BT-111) is required (BR-53).');
        }

        if ($lines === []) {
            throw new InvalidArgumentException('A document needs at least one line (BR-16).');
        }

        if ($taxSubtotals === []) {
            throw new InvalidArgumentException('A document needs at least one VAT breakdown (BR-CO-18).');
        }

        if ($type->requiresPrecedingDocuments() && $precedingDocuments === []) {
            throw new InvalidArgumentException(sprintf('A %s document must reference the invoice it credits or corrects (BT-25).', $type->value));
        }

        self::assertAll($lines, Line::class, 'lines');
        self::assertAll($taxSubtotals, TaxSubtotal::class, 'taxSubtotals');
        self::assertAll($precedingDocuments, PrecedingDocument::class, 'precedingDocuments');
        self::assertAll($paymentMeans, PaymentMeans::class, 'paymentMeans');
        self::assertAll($allowancesCharges, AllowanceCharge::class, 'allowancesCharges');
        self::assertAll($attachments, Attachment::class, 'attachments');

        foreach ($notes as $note) {
            if (! is_string($note)) {
                throw new InvalidArgumentException('Notes must be strings.');
            }
        }
    }

    public function isCreditNote(): bool
    {
        return $this->type->isCreditNote();
    }

    /** True when the buyer is a private person, which routes the upload to /uploadb2c. */
    public function isB2C(): bool
    {
        return $this->buyer->isConsumer;
    }

    /** True when the buyer has no Romanian identifier, which needs `extern=DA` on upload. */
    public function hasForeignBuyer(): bool
    {
        return ! $this->buyer->isConsumer && $this->buyer->cui() === null && ! $this->buyer->address->isRomanian();
    }

    /**
     * @param  array<mixed>  $items
     * @param  class-string  $class
     */
    private static function assertAll(array $items, string $class, string $field): void
    {
        foreach ($items as $item) {
            if (! $item instanceof $class) {
                throw new InvalidArgumentException(sprintf('%s must contain only %s instances.', $field, $class));
            }
        }
    }
}
