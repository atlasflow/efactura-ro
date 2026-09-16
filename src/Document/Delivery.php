<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

use DateTimeImmutable;

/** BG-13: when and where the goods were delivered. Intra-community supplies need at least one of these (BR-IC-11, BR-IC-12). */
final readonly class Delivery
{
    public function __construct(
        public ?DateTimeImmutable $actualDate = null,
        public ?Address $address = null,
        public ?string $partyName = null,
        public ?string $locationId = null,
    ) {}
}
