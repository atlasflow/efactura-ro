<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

use AtlasFlow\EFacturaRo\Support\Amount;

/**
 * BG-22 document totals, all supplied by the caller:
 *
 * - lineExtensionAmount   BT-106  Σ line net amounts
 * - allowanceTotal        BT-107  Σ document-level allowances (null when there are none)
 * - chargeTotal           BT-108  Σ document-level charges (null when there are none)
 * - taxExclusiveAmount    BT-109  BT-106 − BT-107 + BT-108
 * - taxAmount             BT-110  Σ subtotal tax amounts
 * - taxInclusiveAmount    BT-112  BT-109 + BT-110
 * - prepaidAmount         BT-113
 * - payableRoundingAmount BT-114
 * - payableAmount         BT-115  BT-112 − BT-113 + BT-114
 */
final readonly class Totals
{
    public function __construct(
        public Amount $lineExtensionAmount,
        public Amount $taxExclusiveAmount,
        public Amount $taxAmount,
        public Amount $taxInclusiveAmount,
        public Amount $payableAmount,
        public ?Amount $allowanceTotal = null,
        public ?Amount $chargeTotal = null,
        public ?Amount $prepaidAmount = null,
        public ?Amount $payableRoundingAmount = null,
    ) {}
}
