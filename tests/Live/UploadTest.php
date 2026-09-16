<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Anaf\AnafClient;
use AtlasFlow\EFacturaRo\Anaf\Environment;
use AtlasFlow\EFacturaRo\Anaf\PollSchedule;
use AtlasFlow\EFacturaRo\Anaf\SubmissionState;
use AtlasFlow\EFacturaRo\Support\Cui;
use AtlasFlow\EFacturaRo\Tests\Fixtures\Documents;
use AtlasFlow\EFacturaRo\Tests\Fixtures\PsrHttp;
use AtlasFlow\EFacturaRo\Tests\Fixtures\Rebuild;

/*
|--------------------------------------------------------------------------
| ANAF's test environment, live — opt-in
|--------------------------------------------------------------------------
|
| Needs a real OAuth token for api.anaf.ro/test, obtained by a certificate
| holder with SPV rights for ANAF_TEST_CIF. Run with:
|
|   ANAF_TEST_TOKEN=… ANAF_TEST_CIF=… vendor/bin/pest --group=anaf-test
|
| Until this suite has run once, the OAuth client, the authenticated
| endpoints and everything built on them are "not yet run against a live
| ANAF session" (README, release tiers).
|
*/

beforeEach(function () {
    if (getenv('ANAF_TEST_TOKEN') === false || getenv('ANAF_TEST_CIF') === false) {
        $this->markTestSkipped('ANAF_TEST_TOKEN and ANAF_TEST_CIF are not set.');
    }
});

it('uploads, polls to a terminal state and downloads the bundle', function () {
    $token = (string) getenv('ANAF_TEST_TOKEN');
    $cif = Cui::of((string) getenv('ANAF_TEST_CIF'));
    $client = new AnafClient(PsrHttp::client(), PsrHttp::factory(), PsrHttp::factory(), Environment::TEST);

    $document = Documents::standardInvoice();
    $document = Rebuild::with($document, [
        'number' => 'TEST-'.date('YmdHis'),
        'seller' => Rebuild::with($document->seller, ['taxIdentifier' => $cif]),
    ]);

    expect($client->hello($token))->not->toBe('');

    $receipt = $client->uploadDocument($document, $token, cif: $cif);
    expect($receipt->index)->toBeGreaterThan(0);

    $status = null;

    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $status = $client->status($receipt->index, $token);

        if ($status->state->isTerminal()) {
            break;
        }

        sleep(min(30, PollSchedule::nextDelay($attempt)));
    }

    expect($status?->state)->toBeIn([SubmissionState::OK, SubmissionState::NOK]);

    if ($status?->downloadId !== null) {
        $bundle = $client->download($status->downloadId, $token);
        expect($bundle->signatureXml)->not->toBe('')
            ->and($client->verifySignature($bundle->payloadXml, $bundle->signatureXml))->toBeTrue();
    }

    $list = $client->messages($cif, 1, $token);
    expect($list->cui)->toBe($cif->digits());
})->group('anaf-test');
