<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Vat;

/**
 * EN 16931 VAT category codes (UNTDID 5305 subset) as CIUS-RO accepts them.
 */
enum VatCategory: string
{
    /** Standard rated. */
    case S = 'S';

    /** Zero rated. */
    case Z = 'Z';

    /** Exempt from VAT. */
    case E = 'E';

    /** Reverse charge (taxare inversă). */
    case AE = 'AE';

    /** Intra-community supply (livrare intracomunitară). */
    case K = 'K';

    /** Export outside the EU. */
    case G = 'G';

    /** Not subject to VAT (neimpozabil). */
    case O = 'O';

    /** Canary Islands general indirect tax — accepted by the schematron, never used in Romania. */
    case L = 'L';

    /** Ceuta and Melilla tax — accepted by the schematron, never used in Romania. */
    case M = 'M';

    /**
     * BR-E-10, BR-AE-10, BR-IC-10, BR-G-10, BR-O-10: a breakdown in these
     * categories needs an exemption reason code or text.
     */
    public function requiresExemptionReason(): bool
    {
        return in_array($this, [self::E, self::AE, self::K, self::G, self::O], true);
    }

    /** BR-S-10, BR-Z-10: standard and zero rated breakdowns must not carry a reason. */
    public function forbidsExemptionReason(): bool
    {
        return in_array($this, [self::S, self::Z], true);
    }

    /** BR-Z-09, BR-E-09, BR-AE-09, BR-IC-09, BR-G-09, BR-O-09. */
    public function taxAmountMustBeZero(): bool
    {
        return $this !== self::S && $this !== self::L && $this !== self::M;
    }

    /** BR-Z-05.., BR-E-05.., BR-AE-05.., BR-IC-05.., BR-G-05.., BR-O-05: the rate on lines must be 0 (or absent for O). */
    public function rateMustBeZero(): bool
    {
        return $this->taxAmountMustBeZero();
    }

    /** BR-O-05..07: "not subject to VAT" lines and breakdowns carry no rate at all. */
    public function carriesNoRate(): bool
    {
        return $this === self::O;
    }
}
