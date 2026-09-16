<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Vat;

use InvalidArgumentException;

/**
 * Turns a neutral VatTreatment into the EN 16931 category and VATEX code the
 * Romanian validator expects. A helper for callers that persist treatments;
 * the send path itself never calls it — documents arrive with codes already
 * chosen.
 *
 * Legal basis, checked 2026-09-16 against the EN 16931 VATEX list shipped in
 * ro16931-ubl-1.0.9 and the Romanian Fiscal Code (Legea 227/2015):
 *
 * - INTRA_EU_SUPPLY → K + VATEX-EU-IC: art. 138 of Directive 2006/112/EC,
 *   transposed as art. 294 (2) a) Cod fiscal.
 * - REVERSE_CHARGE → AE + VATEX-EU-AE: art. 194–199a of the Directive,
 *   art. 331 Cod fiscal (taxare inversă).
 * - EXPORT → G + VATEX-EU-G: art. 146 of the Directive, art. 294 (1) a)–b)
 *   Cod fiscal.
 * - OUT_OF_SCOPE → O + VATEX-EU-O: outside the scope of the Directive; used
 *   for supplies with no place of taxation in the EU or by non-taxable persons.
 * - EXEMPT → E: the article decides the code (art. 132/135 of the Directive,
 *   art. 292 Cod fiscal — VATEX-EU-132-1x, or VATEX-EU-F for the small
 *   enterprise scheme under art. 310); the caller must supply it.
 * - ZERO_RATED → Z: no reason code is allowed (BR-Z-10).
 * - STANDARD → S: no reason code is allowed (BR-S-10).
 */
final class RomanianVatMapping
{
    public static function for(VatTreatment $treatment, ?string $exemptionCode = null, ?string $exemptionReason = null): VatCode
    {
        return match ($treatment) {
            VatTreatment::STANDARD => new VatCode(VatCategory::S),
            VatTreatment::ZERO_RATED => new VatCode(VatCategory::Z),
            VatTreatment::INTRA_EU_SUPPLY => new VatCode(VatCategory::K, Vatex::EU_IC, $exemptionReason ?? 'Livrare intracomunitară scutită — art. 294 alin. (2) lit. a) Cod fiscal'),
            VatTreatment::REVERSE_CHARGE => new VatCode(VatCategory::AE, Vatex::EU_AE, $exemptionReason ?? 'Taxare inversă — art. 331 Cod fiscal'),
            VatTreatment::EXPORT => new VatCode(VatCategory::G, Vatex::EU_G, $exemptionReason ?? 'Export scutit — art. 294 alin. (1) Cod fiscal'),
            VatTreatment::OUT_OF_SCOPE => new VatCode(VatCategory::O, Vatex::EU_O, $exemptionReason ?? 'Neimpozabil în România'),
            VatTreatment::EXEMPT => self::exempt($exemptionCode, $exemptionReason),
        };
    }

    private static function exempt(?string $exemptionCode, ?string $exemptionReason): VatCode
    {
        if ($exemptionCode === null || $exemptionCode === '') {
            throw new InvalidArgumentException('An EXEMPT treatment needs the article-specific VATEX code (for example VATEX-EU-132-1G, or VATEX-EU-F for the small enterprise scheme).');
        }

        if (! Vatex::isKnown($exemptionCode)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a VATEX code on the EN 16931 list.', $exemptionCode));
        }

        return new VatCode(VatCategory::E, $exemptionCode, $exemptionReason ?? 'Scutit de TVA');
    }
}
