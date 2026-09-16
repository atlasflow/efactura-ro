<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Anaf\AnafClient;
use AtlasFlow\EFacturaRo\Anaf\Environment;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\TransportFailure;
use AtlasFlow\EFacturaRo\Tests\Fixtures\Documents;
use AtlasFlow\EFacturaRo\Tests\Fixtures\PsrHttp;
use AtlasFlow\EFacturaRo\Ubl\UblWriter;

/*
|--------------------------------------------------------------------------
| ANAF's public validator, live
|--------------------------------------------------------------------------
|
| The one test that proves the writer against MF's real schematron. It
| needs no credentials and runs by default; `vendor/bin/pest
| --exclude-group=anaf-public` skips it offline. A network failure is a
| skip, not a red build.
|
| validare has no `extern=DA`, so its identity layer refuses any buyer
| without a Romanian CUI with "nu a fost identificat cui cumparator"
| (ERRIdentif) — after the schematron has passed. For those fixtures the
| assertion is that ERRIdentif is the only message: the document itself
| is right, and only an upload with `extern=DA` can prove the last layer.
|
*/

function liveClient(): AnafClient
{
    return new AnafClient(PsrHttp::client(), PsrHttp::factory(), PsrHttp::factory(), Environment::PRODUCTION);
}

it('is accepted by ANAF\'s validator', function (string $name) {
    $document = Documents::all()[$name]();

    try {
        $result = liveClient()->validateDocument($document);
    } catch (TransportFailure $e) {
        $this->markTestSkipped('ANAF unreachable: '.$e->getMessage());
    }

    if ($document->hasForeignBuyer()) {
        expect($result->codes())->toBe(['ERRIdentif'], 'a foreign buyer can only pass the identity layer at upload with extern=DA; anything else means the schematron failed: '.implode(' | ', array_map('strval', $result->errors)));

        return;
    }

    expect($result->ok)->toBeTrue(implode("\n", array_map('strval', $result->errors)));
})->with(array_keys(Documents::all()))->group('anaf-public');

it('reports a schematron failure with its rule id', function () {
    $xml = (new UblWriter)->write(Documents::standardInvoice());
    $broken = str_replace('<cbc:CountrySubentity>RO-CJ</cbc:CountrySubentity>', '<cbc:CountrySubentity>CJ</cbc:CountrySubentity>', $xml);

    try {
        $result = liveClient()->validate($broken);
    } catch (TransportFailure $e) {
        $this->markTestSkipped('ANAF unreachable: '.$e->getMessage());
    }

    expect($result->ok)->toBeFalse()
        ->and($result->codes())->toContain('BR-RO-110')
        ->and($result->traceId)->not->toBeNull();
})->group('anaf-public');
