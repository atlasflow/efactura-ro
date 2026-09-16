<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

use AtlasFlow\EFacturaRo\Support\Amount;
use InvalidArgumentException;

/**
 * A document-level allowance (BG-20) or charge (BG-21), or a line-level one
 * (BG-27 / BG-28). Document-level entries carry their own VAT category so
 * they can be folded into the right tax subtotal; line-level ones inherit
 * the line's. `amount` is always non-negative; the direction is `isCharge`.
 */
final readonly class AllowanceCharge
{
    public function __construct(
        public bool $isCharge,
        public Amount $amount,
        public ?string $reason = null,
        public ?string $reasonCode = null,
        public ?Amount $baseAmount = null,
        public ?Amount $percentage = null,
        public ?LineVat $vat = null,
    ) {
        if ($reason === null && $reasonCode === null) {
            throw new InvalidArgumentException('An allowance or charge needs a reason or a reason code (BR-CO-21..24).');
        }
    }

    public static function allowance(Amount|string $amount, ?string $reason = null, ?string $reasonCode = null, ?LineVat $vat = null, ?Amount $baseAmount = null, ?Amount $percentage = null): self
    {
        return new self(false, Amount::of($amount), $reason, $reasonCode, $baseAmount, $percentage, $vat);
    }

    public static function charge(Amount|string $amount, ?string $reason = null, ?string $reasonCode = null, ?LineVat $vat = null, ?Amount $baseAmount = null, ?Amount $percentage = null): self
    {
        return new self(true, Amount::of($amount), $reason, $reasonCode, $baseAmount, $percentage, $vat);
    }

    /** The amount with its sign: charges add, allowances subtract. */
    public function signedAmount(): Amount
    {
        return $this->isCharge ? $this->amount : $this->amount->negated();
    }
}
