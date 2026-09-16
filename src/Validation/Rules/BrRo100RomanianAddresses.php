<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Address;
use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationError;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/**
 * The Romanian address rules, applied to the seller, the buyer and the
 * delivery address:
 * - BR-RO-081/082, BR-RO-180: address line 1 (StreetName) is present;
 * - BR-RO-091/092, BR-RO-201: the city is present;
 * - BR-RO-110/111, BR-RO-210: a Romanian address carries an ISO 3166-2:RO
 *   county code (RO-AB … RO-VS, RO-B for Bucharest);
 * - BR-RO-100/101, BR-RO-200: in RO-B the city is one of SECTOR1 … SECTOR6;
 * - BR-RO-211: a delivery address carries a country subdivision whatever
 *   its country — ANAF's validator enforces this on a German address too
 *   (checked 2026-09-16).
 * Source: RO16931-rules.sch in ro16931-ubl-1.0.9.
 */
final class BrRo100RomanianAddresses implements Rule
{
    use Concerns;

    /** @var list<string> */
    public const array COUNTY_CODES = [
        'RO-AB', 'RO-AG', 'RO-AR', 'RO-B', 'RO-BC', 'RO-BH', 'RO-BN', 'RO-BR', 'RO-BT', 'RO-BV', 'RO-BZ', 'RO-CJ', 'RO-CL', 'RO-CS', 'RO-CT',
        'RO-CV', 'RO-DB', 'RO-DJ', 'RO-GJ', 'RO-GL', 'RO-GR', 'RO-HD', 'RO-HR', 'RO-IF', 'RO-IL', 'RO-IS', 'RO-MH', 'RO-MM', 'RO-MS', 'RO-NT',
        'RO-OT', 'RO-PH', 'RO-SB', 'RO-SJ', 'RO-SM', 'RO-SV', 'RO-TL', 'RO-TM', 'RO-TR', 'RO-VL', 'RO-VN', 'RO-VS',
    ];

    /** @var list<string> */
    public const array SECTORS = ['SECTOR1', 'SECTOR2', 'SECTOR3', 'SECTOR4', 'SECTOR5', 'SECTOR6'];

    public function check(Document $document): array
    {
        $errors = [
            ...$this->checkAddress($document->seller->address, 'seller.address', 'BR-RO-081', 'BR-RO-091', 'BR-RO-110', 'BR-RO-100', null),
            ...$this->checkAddress($document->buyer->address, 'buyer.address', 'BR-RO-082', 'BR-RO-092', 'BR-RO-111', 'BR-RO-101', null),
        ];

        if ($document->delivery?->address !== null) {
            $errors = [...$errors, ...$this->checkAddress($document->delivery->address, 'delivery.address', 'BR-RO-180', 'BR-RO-201', 'BR-RO-210', 'BR-RO-200', 'BR-RO-211')];
        }

        return $errors;
    }

    /** @return list<ValidationError> */
    private function checkAddress(Address $address, string $path, string $lineRule, string $cityRule, string $countyRule, string $sectorRule, ?string $subdivisionRule): array
    {
        $errors = [];

        if (trim($address->line) === '') {
            $errors[] = $this->error($lineRule, "$path.line", 'Address line 1 must be provided.', ValidationSource::CIUS_RO);
        }

        if (trim($address->city) === '') {
            $errors[] = $this->error($cityRule, "$path.city", 'The city must be provided.', ValidationSource::CIUS_RO);
        }

        $subdivision = $address->countySubdivision === null ? '' : trim($address->countySubdivision);

        if ($subdivisionRule !== null && $subdivision === '') {
            $errors[] = $this->error($subdivisionRule, "$path.countySubdivision", 'A delivery address needs a country subdivision.', ValidationSource::CIUS_RO);
        }

        if (! $address->isRomanian()) {
            return $errors;
        }

        if (! in_array($subdivision, self::COUNTY_CODES, true)) {
            $errors[] = $this->error($countyRule, "$path.countySubdivision", sprintf('"%s" is not an ISO 3166-2:RO county code (RO-AB … RO-VS, RO-B).', $subdivision), ValidationSource::CIUS_RO);
        } elseif ($subdivision === 'RO-B' && ! in_array(trim($address->city), self::SECTORS, true)) {
            $errors[] = $this->error($sectorRule, "$path.city", sprintf('In Bucharest (RO-B) the city must be one of SECTOR1 … SECTOR6, found "%s".', $address->city), ValidationSource::CIUS_RO);
        }

        return $errors;
    }
}
