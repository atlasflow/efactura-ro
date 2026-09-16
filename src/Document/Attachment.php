<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

/**
 * BG-24: an additional supporting document. Either an external URI or an
 * embedded binary (base64 on the wire, raw bytes here).
 */
final readonly class Attachment
{
    public function __construct(
        public string $id,
        public ?string $description = null,
        public ?string $uri = null,
        public ?string $content = null,
        public ?string $mimeType = null,
        public ?string $filename = null,
    ) {}
}
