<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Vat;

/**
 * The VATEX exemption reason code list (EN 16931 code list, CEF VATEX 1.0 as
 * published with the EN 16931 validation artefacts). Only the codes are
 * listed; their legal texts live with RomanianVatMapping.
 *
 * Source: EN16931-UBL-codes.sch in ro16931-ubl-1.0.9 (MF, 2024-05) — the list
 * ANAF's validator enforces.
 */
final class Vatex
{
    public const string EU_AE = 'VATEX-EU-AE';

    public const string EU_IC = 'VATEX-EU-IC';

    public const string EU_G = 'VATEX-EU-G';

    public const string EU_O = 'VATEX-EU-O';

    public const string EU_D = 'VATEX-EU-D';

    public const string EU_F = 'VATEX-EU-F';

    public const string EU_I = 'VATEX-EU-I';

    public const string EU_J = 'VATEX-EU-J';

    public const string EU_79_C = 'VATEX-EU-79-C';

    public const string EU_309 = 'VATEX-EU-309';

    /** @var list<string> */
    private const array CODES = [
        'VATEX-EU-79-C',
        'VATEX-EU-132',
        'VATEX-EU-132-1A', 'VATEX-EU-132-1B', 'VATEX-EU-132-1C', 'VATEX-EU-132-1D', 'VATEX-EU-132-1E',
        'VATEX-EU-132-1F', 'VATEX-EU-132-1G', 'VATEX-EU-132-1H', 'VATEX-EU-132-1I', 'VATEX-EU-132-1J',
        'VATEX-EU-132-1K', 'VATEX-EU-132-1L', 'VATEX-EU-132-1M', 'VATEX-EU-132-1N', 'VATEX-EU-132-1O',
        'VATEX-EU-132-1P', 'VATEX-EU-132-1Q',
        'VATEX-EU-143',
        'VATEX-EU-143-1A', 'VATEX-EU-143-1B', 'VATEX-EU-143-1C', 'VATEX-EU-143-1D', 'VATEX-EU-143-1E',
        'VATEX-EU-143-1F', 'VATEX-EU-143-1FA', 'VATEX-EU-143-1G', 'VATEX-EU-143-1H', 'VATEX-EU-143-1I',
        'VATEX-EU-143-1J', 'VATEX-EU-143-1K', 'VATEX-EU-143-1L',
        'VATEX-EU-148',
        'VATEX-EU-148-A', 'VATEX-EU-148-B', 'VATEX-EU-148-C', 'VATEX-EU-148-D', 'VATEX-EU-148-E',
        'VATEX-EU-148-F', 'VATEX-EU-148-G',
        'VATEX-EU-151',
        'VATEX-EU-151-1A', 'VATEX-EU-151-1AA', 'VATEX-EU-151-1B', 'VATEX-EU-151-1C', 'VATEX-EU-151-1D',
        'VATEX-EU-151-1E',
        'VATEX-EU-309',
        'VATEX-EU-AE',
        'VATEX-EU-D',
        'VATEX-EU-F',
        'VATEX-EU-G',
        'VATEX-EU-I',
        'VATEX-EU-IC',
        'VATEX-EU-O',
        'VATEX-EU-J',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return self::CODES;
    }

    public static function isKnown(string $code): bool
    {
        return in_array($code, self::CODES, true);
    }
}
