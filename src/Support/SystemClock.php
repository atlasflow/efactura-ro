<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Support;

use DateTimeImmutable;
use DateTimeZone;

final readonly class SystemClock implements Clock
{
    public function __construct(private DateTimeZone $timezone = new DateTimeZone('Europe/Bucharest')) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
