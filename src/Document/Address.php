<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

use InvalidArgumentException;

/**
 * A postal address (BG-5, BG-8, BG-15). For Romania the county goes in
 * `countySubdivision` as ISO 3166-2:RO ("RO-CJ"); Bucharest is "RO-B" with
 * the city spelled SECTOR1…SECTOR6 (BR-RO-100).
 */
final readonly class Address
{
    public function __construct(
        public string $line,
        public string $city,
        public string $country,
        public ?string $countySubdivision = null,
        public ?string $postalCode = null,
        public ?string $line2 = null,
        public ?string $line3 = null,
    ) {
        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            throw new InvalidArgumentException(sprintf('"%s" is not an ISO 3166-1 alpha-2 country code.', $country));
        }
    }

    public function isRomanian(): bool
    {
        return $this->country === 'RO';
    }
}
