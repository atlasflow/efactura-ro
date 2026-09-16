<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Support;

/**
 * Whatever identifies a party to the tax authority: a Romanian CUI, a CNP for
 * a natural person, or a foreign identifier the kernel cannot verify.
 */
interface TaxIdentifier
{
    /** The identifier as it should appear on the wire, without any country prefix. */
    public function value(): string;

    /** ISO 3166-1 alpha-2 country of issue. */
    public function country(): string;
}
