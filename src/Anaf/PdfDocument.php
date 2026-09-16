<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

/** MF's PDF rendering of an XML invoice, from /transformare. */
final readonly class PdfDocument
{
    public function __construct(public string $bytes) {}

    public function size(): int
    {
        return strlen($this->bytes);
    }
}
