<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document\Rasp;

use AtlasFlow\EFacturaRo\Support\Cui;
use DateTimeImmutable;

/**
 * The buyer → seller message ANAF transports as standard RASP: accept,
 * reject or comment on an invoice received in the SPV.
 */
final readonly class RaspMessage
{
    public function __construct(
        public string $invoiceNumber,
        public DateTimeImmutable $invoiceIssueDate,
        public Cui $sellerCui,
        public Cui $buyerCui,
        public RaspType $type,
        public ?string $text = null,
    ) {}
}
