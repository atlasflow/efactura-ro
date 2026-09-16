<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Support;

use InvalidArgumentException;
use Stringable;

/**
 * A tax identifier issued outside Romania, or one the kernel cannot verify
 * (a received invoice may carry a CUI with a bad check digit; the reader keeps
 * it as-is rather than refusing the whole document).
 */
final readonly class ForeignIdentifier implements Stringable, TaxIdentifier
{
    private function __construct(
        private string $value,
        private string $country,
    ) {}

    public static function of(string $value, string $country): self
    {
        $value = trim($value);
        $country = strtoupper(trim($country));

        if ($value === '') {
            throw new InvalidArgumentException('A foreign identifier cannot be empty.');
        }

        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            throw new InvalidArgumentException(sprintf('"%s" is not an ISO 3166-1 alpha-2 country code.', $country));
        }

        return new self($value, $country);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
