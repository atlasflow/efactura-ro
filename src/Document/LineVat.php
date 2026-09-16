<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Vat\VatCategory;
use AtlasFlow\EFacturaRo\Vat\VatCode;

/**
 * BG-30 / BT-151..152: the VAT category and rate of a line, a document-level
 * allowance or a charge. `rate` is a percentage ("19", "9.00"); null only for
 * category O, which carries no rate (BR-O-05).
 *
 * The exemption code and reason are kept here so a caller can build the tax
 * subtotals from the lines; the UBL writer emits them once per subtotal, not
 * per line, as CIUS-RO expects.
 */
final readonly class LineVat
{
    public function __construct(
        public VatCategory $category,
        public ?Amount $rate = null,
        public ?string $exemptionCode = null,
        public ?string $exemptionReason = null,
    ) {}

    public static function standard(Amount|string $rate): self
    {
        return new self(VatCategory::S, Amount::of($rate));
    }

    public static function fromCode(VatCode $code, Amount|string|null $rate = null): self
    {
        $rate ??= $code->category->carriesNoRate() ? null : '0';

        return new self($code->category, $rate === null ? null : Amount::of($rate), $code->exemptionCode, $code->exemptionReason);
    }

    /** The subtotal this VAT belongs to: same category and same rate. */
    public function key(): string
    {
        return $this->category->value.'@'.($this->rate?->rounded(2)->toString() ?? '-');
    }
}
