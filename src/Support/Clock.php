<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Support;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
