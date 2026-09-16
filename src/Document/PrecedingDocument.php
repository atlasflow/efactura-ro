<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

use DateTimeImmutable;

/** BG-3: a reference to the invoice being credited or corrected (BT-25, BT-26). */
final readonly class PrecedingDocument
{
    public function __construct(
        public string $number,
        public ?DateTimeImmutable $issueDate = null,
    ) {}
}
