<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Codul numeric personal — the 13-digit identifier of a Romanian natural person.
 *
 * Structure S AA LL ZZ JJ NNN C: sex/century, year, month, day, county, serial,
 * check digit. The check digit is the weighted sum against 279146358279
 * modulo 11, with 10 mapped to 1.
 */
final readonly class Cnp implements Stringable, TaxIdentifier
{
    private const string KEY = '279146358279';

    /** What CIUS-RO wants on a B2C invoice when the consumer gave no CNP. */
    public const string PLACEHOLDER = '0000000000000';

    private function __construct(private string $digits) {}

    public static function of(string $value): self
    {
        $digits = trim($value);

        if (! preg_match('/^\d{13}$/', $digits)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a CNP: expected exactly 13 digits.', $value));
        }

        if (! self::structureHolds($digits) || ! self::checkDigitHolds($digits)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a CNP: the structure or check digit does not match.', $value));
        }

        return new self($digits);
    }

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

    public function value(): string
    {
        return $this->digits;
    }

    public function country(): string
    {
        return 'RO';
    }

    public function __toString(): string
    {
        return $this->digits;
    }

    private static function structureHolds(string $digits): bool
    {
        $sex = (int) $digits[0];
        $month = (int) substr($digits, 3, 2);
        $day = (int) substr($digits, 5, 2);
        $county = (int) substr($digits, 7, 2);

        return $sex >= 1 && $sex <= 9
            && $month >= 1 && $month <= 12
            && $day >= 1 && $day <= 31
            && (($county >= 1 && $county <= 52) || $county === 70);
    }

    private static function checkDigitHolds(string $digits): bool
    {
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $digits[$i]) * ((int) self::KEY[$i]);
        }

        $expected = $sum % 11;

        if ($expected === 10) {
            $expected = 1;
        }

        return $expected === (int) $digits[12];
    }
}
