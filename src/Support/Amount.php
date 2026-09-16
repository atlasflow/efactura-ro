<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Stringable;

/**
 * A monetary amount or any other decimal quantity the document carries.
 *
 * Amounts enter the kernel as decimal strings and stay exact: the wrapper is
 * a BigDecimal, construction from a float is refused, and nothing in the
 * package ever rounds a caller-supplied figure. The scale given at
 * construction is preserved, so "12.30" is written back as "12.30".
 */
final readonly class Amount implements Stringable
{
    private function __construct(private BigDecimal $value) {}

    /**
     * @param  string|int|BigDecimal|Amount  $value  a decimal string such as "12.30" or "-0.5"; floats are refused
     */
    public static function of(string|int|BigDecimal|Amount|float $value): self
    {
        if (is_float($value)) {
            throw new InvalidArgumentException('Amounts are built from decimal strings, never floats; pass "'.self::floatHint($value).'" instead.');
        }

        if ($value instanceof self) {
            return $value;
        }

        if ($value instanceof BigDecimal) {
            return new self($value);
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || ! preg_match('/^[+-]?\d+(\.\d+)?$/', $value)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a plain decimal string.', $value));
            }
        }

        return new self(BigDecimal::of($value));
    }

    public static function zero(int $scale = 2): self
    {
        return new self(BigDecimal::zero()->toScale($scale));
    }

    /**
     * Sum a list of amounts; an empty list sums to zero at the given scale.
     *
     * @param  iterable<Amount>  $amounts
     */
    public static function sum(iterable $amounts, int $scale = 2): self
    {
        $total = BigDecimal::zero();

        foreach ($amounts as $amount) {
            $total = $total->plus($amount->value);
        }

        return new self($total->toScale(max($scale, $total->getScale())));
    }

    public function plus(Amount|string|int $other): self
    {
        return new self($this->value->plus(self::of($other)->value));
    }

    public function minus(Amount|string|int $other): self
    {
        return new self($this->value->minus(self::of($other)->value));
    }

    public function multipliedBy(Amount|string|int $other): self
    {
        return new self($this->value->multipliedBy(self::of($other)->value));
    }

    public function dividedBy(Amount|string|int $other, int $scale): self
    {
        return new self($this->value->dividedBy(self::of($other)->value, $scale, RoundingMode::HalfUp));
    }

    /** Round half away from zero to the given number of decimals (EN 16931 "rounded to two decimals"). */
    public function rounded(int $scale = 2): self
    {
        return new self($this->value->toScale($scale, RoundingMode::HalfUp));
    }

    public function abs(): self
    {
        return new self($this->value->abs());
    }

    public function negated(): self
    {
        return new self($this->value->negated());
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function isNegative(): bool
    {
        return $this->value->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->value->isPositive();
    }

    /** Numeric equality: "12.3" equals "12.30". */
    public function equals(Amount|string|int $other): bool
    {
        return $this->value->isEqualTo(self::of($other)->value);
    }

    public function compareTo(Amount|string|int $other): int
    {
        return $this->value->compareTo(self::of($other)->value);
    }

    public function isGreaterThan(Amount|string|int $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isLessThan(Amount|string|int $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    /** Number of digits after the decimal point as supplied. */
    public function scale(): int
    {
        return $this->value->getScale();
    }

    public function toBigDecimal(): BigDecimal
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value->__toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function floatHint(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }
}
