<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

use DateTimeImmutable;
use InvalidArgumentException;

/** BG-14 / BG-26: an invoicing period; at least one bound is required (BR-CO-19, BR-CO-20). */
final readonly class Period
{
    public function __construct(
        public ?DateTimeImmutable $start = null,
        public ?DateTimeImmutable $end = null,
        public ?string $descriptionCode = null,
    ) {
        if ($start === null && $end === null && $descriptionCode === null) {
            throw new InvalidArgumentException('A period needs a start date, an end date or a description code.');
        }
    }
}
