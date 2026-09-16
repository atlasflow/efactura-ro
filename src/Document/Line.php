<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

use AtlasFlow\EFacturaRo\Support\Amount;
use InvalidArgumentException;

/**
 * An invoice line (BG-25). `netAmount` is BT-131, supplied by the caller and
 * verified (never computed) by the arithmetic rules: quantity × unitPrice ÷
 * priceBaseQuantity, minus line allowances, plus line charges.
 *
 * `unitCode` is UN/ECE Recommendation 20; H87 is "piece", C62 is "one" (the
 * MF samples use C62), KGM kilogram, LTR litre, MTR metre.
 */
final readonly class Line
{
    /**
     * @param  list<AllowanceCharge>  $allowancesCharges
     */
    public function __construct(
        public string $id,
        public string $name,
        public Amount $quantity,
        public Amount $unitPrice,
        public Amount $netAmount,
        public LineVat $vat,
        public string $unitCode = 'H87',
        public ?string $description = null,
        public ?Amount $priceBaseQuantity = null,
        public ?Classification $classification = null,
        public ?string $sellerItemId = null,
        public ?string $note = null,
        public ?Period $period = null,
        public ?string $buyerAccountingReference = null,
        public array $allowancesCharges = [],
    ) {
        if ($unitPrice->isNegative()) {
            throw new InvalidArgumentException(sprintf('Line %s: the item net price (BT-146) must not be negative (BR-27).', $id));
        }

        foreach ($allowancesCharges as $entry) {
            if (! $entry instanceof AllowanceCharge) {
                throw new InvalidArgumentException('Line allowances and charges must be AllowanceCharge instances.');
            }
        }
    }

    /** Quantity × price ÷ base quantity, before line allowances and charges, unrounded. */
    public function grossAmount(): Amount
    {
        $base = $this->priceBaseQuantity ?? Amount::of('1');

        return $this->quantity->multipliedBy($this->unitPrice)->dividedBy($base, 10);
    }

    /** The sum of signed line allowances and charges. */
    public function allowanceChargeTotal(): Amount
    {
        return Amount::sum(array_map(fn (AllowanceCharge $ac) => $ac->signedAmount(), $this->allowancesCharges));
    }
}
