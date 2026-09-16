<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

/**
 * BT-34 / BT-49: an electronic address. EN 16931 needs a scheme identifier
 * (BR-62, BR-63); an email address uses scheme "EM".
 */
final readonly class Endpoint
{
    public function __construct(
        public string $value,
        public string $schemeId = 'EM',
    ) {}

    public static function email(string $address): self
    {
        return new self($address, 'EM');
    }
}
