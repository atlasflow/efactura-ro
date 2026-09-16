<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Anaf\BundleKind;
use AtlasFlow\EFacturaRo\Anaf\DocumentStandard;
use AtlasFlow\EFacturaRo\Anaf\Environment;
use AtlasFlow\EFacturaRo\Anaf\ErrorsBundle;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\NotFound;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\RateLimited;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\RenderRefused;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\TransportFailure;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\Unauthorised;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\UnexpectedResponse;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\UploadRefused;
use AtlasFlow\EFacturaRo\Anaf\MessageFilter;
use AtlasFlow\EFacturaRo\Anaf\MessageType;
use AtlasFlow\EFacturaRo\Anaf\SubmissionState;
use AtlasFlow\EFacturaRo\Anaf\UploadOptions;
use AtlasFlow\EFacturaRo\Anaf\UploadStandard;
use AtlasFlow\EFacturaRo\Support\Cui;
use AtlasFlow\EFacturaRo\Tests\Fixtures\AnafHarness;
use AtlasFlow\EFacturaRo\Tests\Fixtures\Documents;
use AtlasFlow\EFacturaRo\Ubl\UblWriter;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;
use Nyholm\Psr7\Request;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

function anaf(Environment $environment = Environment::TEST): AnafHarness
{
    return new AnafHarness($environment);
}

function zipOf(array $files): string
{
    $path = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();
    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

it('uploads to the environment root with the standard, cif and flags on the query', function () {
    $h = anaf()->willAnswerFixture(200, 'upload-ok.xml');

    $receipt = $h->client->upload('<Invoice/>', UploadStandard::UBL, Cui::of('RO12345674'), new UploadOptions(foreignBuyer: true), 'tok');
    $request = $h->lastRequest();

    expect($receipt->index)->toBe(5001130147)
        ->and($receipt->receivedAt->format('Y-m-d H:i'))->toBe('2026-09-16 11:40')
        ->and($request->getMethod())->toBe('POST')
        ->and($request->getUri()->getHost())->toBe('api.anaf.ro')
        ->and($request->getUri()->getPath())->toBe('/test/FCTEL/rest/upload')
        ->and($h->lastQuery())->toBe(['standard' => 'UBL', 'cif' => '12345674', 'extern' => 'DA'])
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer tok')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/xml')
        ->and((string) $request->getBody())->toBe('<Invoice/>');
});

it('routes consumers to uploadb2c and production to the prod root', function () {
    $h = anaf(Environment::PRODUCTION)->willAnswerFixture(200, 'upload-ok.xml');

    $h->client->uploadDocument(Documents::b2cWithCnp(), 'tok');

    expect($h->lastRequest()->getUri()->getPath())->toBe('/prod/FCTEL/rest/uploadb2c')
        ->and($h->lastQuery()['standard'])->toBe('UBL')
        ->and($h->lastQuery()['cif'])->toBe('12345674')
        ->and((string) $h->lastRequest()->getBody())->toContain('<cbc:CustomizationID>');
});

it('uploads a credit note as CN and a self-billed invoice with autofactura', function () {
    $h = anaf()->willAnswerFixture(200, 'upload-ok.xml');
    $h->client->uploadDocument(Documents::creditNote(), 'tok');
    expect($h->lastQuery()['standard'])->toBe('CN');

    $options = UploadOptions::for(Documents::intraEuSupplyInEur());
    expect($options->foreignBuyer)->toBeTrue()->and($options->b2c)->toBeFalse()
        ->and((new UploadOptions(selfBilled: true, enforcement: true))->query())->toBe(['autofactura' => 'DA', 'executare' => 'DA']);
});

it('throws UploadRefused with ANAF messages when ExecutionStatus is 1', function () {
    $h = anaf()->willAnswerFixture(200, 'upload-refused-too-large.xml');

    try {
        $h->client->upload('<x/>', UploadStandard::UBL, Cui::of('12345674'), new UploadOptions, 'tok');
        $this->fail('expected UploadRefused');
    } catch (UploadRefused $e) {
        expect($e->messages)->toBe(['Marime fisier transmis mai mare de 10 MB.'])
            ->and($e->body)->toContain('ExecutionStatus="1"');
    }
});

it('maps a "no rights" refusal at upload to Unauthorised', function () {
    anaf()->willAnswerFixture(200, 'upload-refused-no-rights.xml')
        ->client->upload('<x/>', UploadStandard::UBL, Cui::of('12345674'), new UploadOptions, 'tok');
})->throws(Unauthorised::class, 'Nu aveti drept in SPV pentru CIF=1234');

it('reads the four stareMesaj states', function (string $fixture, SubmissionState $state, ?string $downloadId) {
    $status = anaf()->willAnswerFixture(200, $fixture)->client->status(5001130147, 'tok');

    expect($status->state)->toBe($state)
        ->and($status->downloadId)->toBe($downloadId);
})->with([
    ['stare-ok.xml', SubmissionState::OK, '1234'],
    ['stare-nok.xml', SubmissionState::NOK, '123'],
    ['stare-processing.xml', SubmissionState::PROCESSING, null],
    ['stare-refused.xml', SubmissionState::REFUSED, null],
]);

it('sends id_incarcare on the stareMesaj query', function () {
    $h = anaf()->willAnswerFixture(200, 'stare-ok.xml');
    $h->client->status(42, 'tok');

    expect($h->lastRequest()->getUri()->getPath())->toBe('/test/FCTEL/rest/stareMesaj')
        ->and($h->lastQuery())->toBe(['id_incarcare' => '42']);
});

it('maps stareMesaj error headers to typed exceptions', function (string $fixture, string $exception) {
    anaf()->willAnswerFixture(200, $fixture)->client->status(1, 'tok');
})->with([
    ['stare-not-found.xml', NotFound::class],
    ['stare-quota.xml', RateLimited::class],
    ['stare-no-rights.xml', Unauthorised::class],
])->throws(Exception::class);

it('names the quota that was hit', function () {
    try {
        anaf()->willAnswerFixture(200, 'stare-quota.xml')->client->status(1, 'tok');
    } catch (RateLimited $e) {
        expect($e->quota)->toBe('downloads-per-message-per-day')
            ->and($e->httpStatus)->toBe(200);
    }
});

it('lists messages with their types and the CUIs parsed from details', function () {
    $h = anaf()->willAnswerFixture(200, 'lista-ok.json');

    $list = $h->client->messages(Cui::of('12345674'), 5, 'tok', MessageFilter::SENT);

    expect($h->lastRequest()->getUri()->getPath())->toBe('/test/FCTEL/rest/listaMesajeFactura')
        ->and($h->lastQuery())->toBe(['zile' => '5', 'cif' => '12345674', 'filtru' => 'T'])
        ->and($list->messages)->toHaveCount(4)
        ->and($list->cui)->toBe('12345674')
        ->and($list->messages[0]->type)->toBe(MessageType::INVOICE_SENT)
        ->and($list->messages[0]->id)->toBe('3001293434')
        ->and($list->messages[0]->requestIndex)->toBe('5001130147')
        ->and($list->messages[0]->sellerCui)->toBe('12345674')
        ->and($list->messages[0]->buyerCui)->toBe('40000000')
        ->and($list->messages[0]->createdAt->format('Y-m-d H:i'))->toBe('2026-09-15 14:52')
        ->and($list->messages[1]->type)->toBe(MessageType::INVOICE_ERRORS)
        ->and($list->messages[1]->sellerCui)->toBeNull()
        ->and($list->messages[2]->type)->toBe(MessageType::INVOICE_RECEIVED)
        ->and($list->messages[2]->sellerCui)->toBe('11223342')
        ->and($list->messages[3]->type->isBuyerMessage())->toBeTrue();
});

it('clamps the day window to 1..60 and treats "no messages" as an empty list', function () {
    $h = anaf()->willAnswerFixture(200, 'lista-empty.json');

    $list = $h->client->messages(Cui::of('12345674'), 99, 'tok');

    expect($h->lastQuery()['zile'])->toBe('60')
        ->and($list->isEmpty())->toBeTrue();
});

it('maps list errors inside a 200 to Unauthorised and RateLimited', function () {
    expect(fn () => anaf()->willAnswerFixture(200, 'lista-no-rights.json')->client->messages(Cui::of('12345674'), 1, 'tok'))
        ->toThrow(Unauthorised::class)
        ->and(fn () => anaf()->willAnswerFixture(200, 'lista-quota.json')->client->messages(Cui::of('12345674'), 1, 'tok'))
        ->toThrow(RateLimited::class);
});

it('pages through the paginated list with millisecond timestamps', function () {
    $h = anaf()->willAnswerFixture(200, 'lista-paginated.json');

    $from = new DateTimeImmutable('2026-09-01 00:00:00 UTC');
    $to = new DateTimeImmutable('2026-09-16 00:00:00 UTC');
    $page = $h->client->messagesBetween(Cui::of('12345674'), $from, $to, 29, 'tok', MessageFilter::ERRORS);

    expect($h->lastRequest()->getUri()->getPath())->toBe('/test/FCTEL/rest/listaMesajePaginatieFactura')
        ->and($h->lastQuery())->toBe(['startTime' => $from->getTimestamp().'000', 'endTime' => $to->getTimestamp().'000', 'cif' => '12345674', 'pagina' => '29', 'filtru' => 'E'])
        ->and($page->page)->toBe(29)
        ->and($page->totalPages)->toBe(29)
        ->and($page->totalRecords)->toBe(14130)
        ->and($page->perPage)->toBe(500)
        ->and($page->hasMore())->toBeFalse()
        ->and($page->messages)->toHaveCount(2);
});

it('downloads and unpacks the signed bundle, telling an invoice from an error list', function () {
    $invoiceXml = (new UblWriter)->write(Documents::standardInvoice());
    $h = anaf()->willAnswer(200, zipOf(['5001130147.xml' => $invoiceXml, 'semnatura_5001130147.xml' => '<Signature/>']), 'application/zip');

    $bundle = $h->client->download('3001293434', 'tok');

    expect($h->lastQuery())->toBe(['id' => '3001293434'])
        ->and($bundle->kind)->toBe(BundleKind::INVOICE)
        ->and($bundle->payloadFilename)->toBe('5001130147.xml')
        ->and($bundle->signatureFilename)->toBe('semnatura_5001130147.xml')
        ->and($bundle->payloadXml)->toBe($invoiceXml)
        ->and($bundle->signatureXml)->toBe('<Signature/>');

    $errors = anaf()->willAnswer(200, zipOf(['5001120362.xml' => '<header><Error errorMessage="BR-CO-10 failed"/></header>', 'semnatura_5001120362.xml' => '<Signature/>']), 'application/zip')
        ->client->download('3001474425', 'tok');

    expect($errors->kind)->toBe(BundleKind::ERRORS)
        ->and(ErrorsBundle::messages($errors->payloadXml))->toBe(['BR-CO-10 failed']);
});

it('maps download errors inside a 200 and refuses a non-ZIP body', function () {
    expect(fn () => anaf()->willAnswerFixture(200, 'descarcare-quota.json')->client->download('1', 'tok'))->toThrow(RateLimited::class)
        ->and(fn () => anaf()->willAnswerFixture(200, 'descarcare-not-found.json')->client->download('1', 'tok'))->toThrow(NotFound::class)
        ->and(fn () => anaf()->willAnswer(200, 'not a zip', 'application/octet-stream')->client->download('1', 'tok'))->toThrow(UnexpectedResponse::class, 'not a ZIP');
});

it('validates on the public host without credentials and picks FACT1 or FCN from the XML', function () {
    $h = anaf()->willAnswerFixture(200, 'validare-ok.json');

    $result = $h->client->validateDocument(Documents::creditNote());

    expect($result->ok)->toBeTrue()
        ->and($result->traceId)->toBe('aa0f2c2c-0190-42f8-8e71-5c7bdf396888')
        ->and($h->lastRequest()->getUri()->getHost())->toBe('webservicesp.anaf.ro')
        ->and($h->lastRequest()->getUri()->getPath())->toBe('/prod/FCTEL/rest/validare/FCN')
        ->and($h->lastRequest()->hasHeader('Authorization'))->toBeFalse()
        ->and($h->lastRequest()->getHeaderLine('Content-Type'))->toBe('text/plain');

    $h->willAnswerFixture(200, 'validare-ok.json');
    $h->client->validate('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>');
    expect($h->lastRequest()->getUri()->getPath())->toBe('/prod/FCTEL/rest/validare/FACT1');
});

it('parses structured and legacy validare messages into codes, paths and text', function () {
    $structured = anaf()->willAnswerFixture(200, 'validare-nok-structured.json')->client->validate('<x/>', DocumentStandard::INVOICE);
    $legacy = anaf()->willAnswerFixture(200, 'validare-nok-legacy.json')->client->validate('<x/>', DocumentStandard::INVOICE);
    $xsd = anaf()->willAnswerFixture(200, 'validare-xsd.json')->client->validate('<x/>', DocumentStandard::INVOICE);

    expect($structured->ok)->toBeFalse()
        ->and($structured->codes())->toBe(['BR-CO-26', 'ERRIdentif'])
        ->and($structured->errors[0]->path)->toBe('/Invoice/cac:AccountingSupplierParty')
        ->and($structured->errors[0]->message)->toStartWith('[BR-CO-26]-In order for the buyer')
        ->and($structured->errors[0]->source)->toBe(ValidationSource::ANAF)
        ->and($structured->errors[1]->message)->toBe('CUI cumparator incorect')
        ->and($legacy->codes())->toBe(['BR-CO-09', 'R_BT_32_cui'])
        ->and($legacy->errors[1]->message)->toContain('Cui vanzator incorect')
        ->and($xsd->codes())->toBe(['ANAF'])
        ->and($xsd->errors[0]->message)->toContain('SAXParseException');
});

it('renders a PDF and raises RenderRefused when ANAF validates first and fails', function () {
    $h = anaf()->willAnswer(200, '%PDF-1.4 fake', 'application/pdf');
    $pdf = $h->client->render('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>');

    expect($pdf->bytes)->toStartWith('%PDF')
        ->and($h->lastRequest()->getUri()->getPath())->toBe('/prod/FCTEL/rest/transformare/FACT1');

    $h->willAnswer(200, '%PDF-1.4 fake', 'application/pdf');
    $h->client->render('<CreditNote xmlns="x"/>', null, true);
    expect($h->lastRequest()->getUri()->getPath())->toBe('/prod/FCTEL/rest/transformare/FCN/DA');

    try {
        anaf()->willAnswerFixture(200, 'validare-nok-legacy.json')->client->render('<x/>', DocumentStandard::INVOICE);
        $this->fail('expected RenderRefused');
    } catch (RenderRefused $e) {
        expect($e->validation->codes())->toContain('BR-CO-09');
    }
});

it('verifies a signature through multipart and reads MF\'s yes or no', function () {
    $h = anaf()->willAnswerFixture(200, 'signature-ok.json');
    $ok = $h->client->verifySignature('<Invoice/>', '<Signature/>');
    $request = $h->lastRequest();

    expect($ok)->toBeTrue()
        ->and($request->getUri()->getPath())->toBe('/api/validate/signature')
        ->and($request->getHeaderLine('Content-Type'))->toStartWith('multipart/form-data; boundary=')
        ->and((string) $request->getBody())->toContain('name="file"; filename="invoice.xml"')
        ->and((string) $request->getBody())->toContain('name="signature"; filename="signature.xml"')
        ->and(anaf()->willAnswerFixture(200, 'signature-nok.json')->client->verifySignature('<a/>', '<b/>'))->toBeFalse();
});

it('says hello with the token', function () {
    $h = anaf()->willAnswer(200, 'Salut efactura-ro', 'text/plain');

    expect($h->client->hello('tok'))->toBe('Salut efactura-ro')
        ->and($h->lastRequest()->getUri()->getPath())->toBe('/TestOauth/jaxrs/hello')
        ->and($h->lastQuery())->toBe(['name' => 'efactura-ro']);
});

it('maps HTTP 403, 429 with Retry-After, 404 and 5xx', function () {
    expect(fn () => anaf()->willAnswerFixture(403, 'http-403.json')->client->status(1, 'tok'))->toThrow(Unauthorised::class, 'Access Denied')
        ->and(fn () => anaf()->willAnswerFixture(404, 'http-403.json')->client->status(1, 'tok'))->toThrow(NotFound::class)
        ->and(fn () => anaf()->willAnswer(502, '<html>bad gateway</html>', 'text/html')->client->status(1, 'tok'))->toThrow(TransportFailure::class);

    try {
        anaf()->willAnswer(429, file_get_contents(__DIR__.'/../fixtures/anaf/http-429.json'), 'application/json', ['Retry-After' => '30'])->client->status(1, 'tok');
    } catch (RateLimited $e) {
        expect($e->retryAfter)->toBe(30)
            ->and($e->httpStatus)->toBe(429)
            ->and($e->getMessage())->toBe('Rate limit exceeded');
    }
});

it('turns a PSR-18 failure into TransportFailure', function () {
    $h = anaf();
    $h->http->addException(new class('connection reset') extends RuntimeException implements Http\Client\Exception, NetworkExceptionInterface
    {
        public function getRequest(): RequestInterface
        {
            return new Request('GET', 'https://api.anaf.ro');
        }
    });

    $h->client->status(1, 'tok');
})->throws(TransportFailure::class, 'connection reset');
