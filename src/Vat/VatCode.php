<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Vat;

/**
 * What goes on the wire for one VAT treatment: the category, and for the
 * exempt-like categories the VATEX reason code and its default text.
 */
final readonly class VatCode
{
    public function __construct(
        public VatCategory $category,
        public ?string $exemptionCode = null,
        public ?string $exemptionReason = null,
    ) {}
}
