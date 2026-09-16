<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

/** BG-6 / BG-9: a contact point on a party. */
final readonly class Contact
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->name === null && $this->email === null && $this->phone === null;
    }
}
