<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

use DateTimeImmutable;

/** What /upload returns when ExecutionStatus is 0: the index to poll and download by. */
final readonly class UploadReceipt
{
    public function __construct(
        public int $index,
        public DateTimeImmutable $receivedAt,
    ) {}
}
