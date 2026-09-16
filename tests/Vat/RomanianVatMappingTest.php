<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Vat\RomanianVatMapping;
use AtlasFlow\EFacturaRo\Vat\VatCategory;
use AtlasFlow\EFacturaRo\Vat\Vatex;
use AtlasFlow\EFacturaRo\Vat\VatTreatment;

it('maps each treatment to its category and reason code', function (VatTreatment $treatment, VatCategory $category, ?string $code) {
    $vat = RomanianVatMapping::for($treatment, $treatment === VatTreatment::EXEMPT ? 'VATEX-EU-132-1G' : null);

    expect($vat->category)->toBe($category)
        ->and($vat->exemptionCode)->toBe($code);
})->with([
    'standard' => [VatTreatment::STANDARD, VatCategory::S, null],
    'zero rated' => [VatTreatment::ZERO_RATED, VatCategory::Z, null],
    'intra-EU' => [VatTreatment::INTRA_EU_SUPPLY, VatCategory::K, Vatex::EU_IC],
    'reverse charge' => [VatTreatment::REVERSE_CHARGE, VatCategory::AE, Vatex::EU_AE],
    'export' => [VatTreatment::EXPORT, VatCategory::G, Vatex::EU_G],
    'out of scope' => [VatTreatment::OUT_OF_SCOPE, VatCategory::O, Vatex::EU_O],
    'exempt' => [VatTreatment::EXEMPT, VatCategory::E, 'VATEX-EU-132-1G'],
]);

it('gives exempt-like categories a default reason text the caller can override', function () {
    expect(RomanianVatMapping::for(VatTreatment::REVERSE_CHARGE)->exemptionReason)->toContain('art. 331')
        ->and(RomanianVatMapping::for(VatTreatment::REVERSE_CHARGE, null, 'Reverse charge')->exemptionReason)->toBe('Reverse charge')
        ->and(RomanianVatMapping::for(VatTreatment::STANDARD)->exemptionReason)->toBeNull();
});

it('requires an article-specific VATEX code for EXEMPT', function () {
    RomanianVatMapping::for(VatTreatment::EXEMPT);
})->throws(InvalidArgumentException::class, 'VATEX code');

it('refuses a VATEX code that is not on the list', function () {
    RomanianVatMapping::for(VatTreatment::EXEMPT, 'VATEX-RO-MADE-UP');
})->throws(InvalidArgumentException::class, 'not a VATEX code');

it('knows which categories need or forbid an exemption reason', function () {
    expect(VatCategory::E->requiresExemptionReason())->toBeTrue()
        ->and(VatCategory::K->requiresExemptionReason())->toBeTrue()
        ->and(VatCategory::S->requiresExemptionReason())->toBeFalse()
        ->and(VatCategory::S->forbidsExemptionReason())->toBeTrue()
        ->and(VatCategory::Z->forbidsExemptionReason())->toBeTrue()
        ->and(VatCategory::Z->taxAmountMustBeZero())->toBeTrue()
        ->and(VatCategory::S->taxAmountMustBeZero())->toBeFalse()
        ->and(VatCategory::O->carriesNoRate())->toBeTrue();
});
