<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

/** One /stareMesaj answer: the state and, once terminal, the id to download the bundle with. */
final readonly class SubmissionStatus
{
    public function __construct(
        public SubmissionState $state,
        public ?string $downloadId = null,
    ) {}
}
