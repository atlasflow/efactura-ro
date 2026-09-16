<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Support;

use DateTimeImmutable;

/** A clock that answers the same instant every time — for tests and replay. */
final readonly class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $instant) {}

    public static function at(string $instant): self
    {
        return new self(new DateTimeImmutable($instant));
    }

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }
}
