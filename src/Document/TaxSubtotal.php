<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Vat\VatCategory;

/**
 * One VAT breakdown (BG-23): everything on the document in one category at
 * one rate. Supplied by the caller; the arithmetic rules check it against the
 * lines and the document-level allowances and charges.
 */
final readonly class TaxSubtotal
{
    public function __construct(
        public VatCategory $category,
        public ?Amount $rate,
        public Amount $taxableAmount,
        public Amount $taxAmount,
        public ?string $exemptionCode = null,
        public ?string $exemptionReason = null,
    ) {}

    public function key(): string
    {
        return $this->category->value.'@'.($this->rate?->rounded(2)->toString() ?? '-');
    }
}
