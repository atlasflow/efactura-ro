<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Support\Amount;
use AtlasFlow\EFacturaRo\Validation\ValidationError;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/** Small helpers shared by the rule classes. */
trait Concerns
{
    private function error(string $code, string $path, string $message, ValidationSource $source): ValidationError
    {
        return new ValidationError($code, $path, $message, $source);
    }

    /** Both sides rounded to two decimals, as every EN 16931 sum rule does. */
    private function sameCents(Amount $left, Amount $right): bool
    {
        return $left->rounded(2)->equals($right->rounded(2));
    }

    /**
     * ANAF's tolerance on BR-CO-17, BR-S-08 and BR-S-09: the difference must be
     * strictly less than one unit of currency, not exactly zero.
     */
    private function withinOneUnit(Amount $actual, Amount $expected): bool
    {
        $difference = $actual->abs()->minus($expected->abs()).'';

        return Amount::of($difference)->abs()->isLessThan('1');
    }
}
