<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Codul unic de înregistrare — the Romanian fiscal identifier of a legal entity.
 *
 * Accepts "RO12345678", "ro 12345678" or "12345678" and keeps only the digits.
 * The check digit is verified with the algorithm published by the Ministry of
 * Finance: left-pad the first n-1 digits with zeros to nine, multiply them
 * position by position with the key 753217532, multiply the sum by 10, take
 * modulo 11, and map a remainder of 10 to 0.
 */
final readonly class Cui implements Stringable, TaxIdentifier
{
    private const string KEY = '753217532';

    private function __construct(private string $digits) {}

    public static function of(string $value): self
    {
        $digits = preg_replace('/^\s*RO\s*/i', '', trim($value)) ?? '';

        if (! preg_match('/^\d{2,10}$/', $digits)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a CUI: expected 2 to 10 digits, optionally prefixed with RO.', $value));
        }

        if (! self::checkDigitHolds($digits)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a CUI: the check digit does not match.', $value));
        }

        return new self($digits);
    }

    /** Whether a string is a well-formed CUI with a valid check digit. */
    public static function isValid(string $value): bool
    {
        try {
            self::of($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function digits(): string
    {
        return $this->digits;
    }

    /** The VAT identifier form: "RO" followed by the digits. */
    public function withPrefix(): string
    {
        return 'RO'.$this->digits;
    }

    public function value(): string
    {
        return $this->digits;
    }

    public function country(): string
    {
        return 'RO';
    }

    public function equals(Cui $other): bool
    {
        return $this->digits === $other->digits;
    }

    public function __toString(): string
    {
        return $this->digits;
    }

    private static function checkDigitHolds(string $digits): bool
    {
        $body = str_pad(substr($digits, 0, -1), 9, '0', STR_PAD_LEFT);
        $check = (int) substr($digits, -1);

        $sum = 0;

        foreach (str_split($body) as $i => $digit) {
            $sum += ((int) $digit) * ((int) self::KEY[$i]);
        }

        $expected = ($sum * 10) % 11;

        if ($expected === 10) {
            $expected = 0;
        }

        return $expected === $check;
    }
}
