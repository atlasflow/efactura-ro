<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

use AtlasFlow\EFacturaRo\Anaf\Exceptions\AnafException;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\NotFound;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\RateLimited;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\RenderRefused;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\TransportFailure;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\Unauthorised;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\UnexpectedResponse;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\UploadRefused;
use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Support\Clock;
use AtlasFlow\EFacturaRo\Support\Cui;
use AtlasFlow\EFacturaRo\Support\SystemClock;
use AtlasFlow\EFacturaRo\Ubl\UblWriter;
use AtlasFlow\EFacturaRo\Validation\ValidationResult;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * ANAF's e-Factura REST API over PSR-18. Stateless: every authenticated call
 * takes the bearer token as an argument and the kernel never stores one.
 * The public calls (validate, render, verifySignature) need no token and
 * always go to production — there is no test validator.
 *
 * Errors: HTTP 401/403 and "no rights" texts → Unauthorised; HTTP 429 and
 * ANAF's daily-quota texts → RateLimited; 404 and "no such invoice" texts →
 * NotFound; 5xx and PSR-18 failures → TransportFailure (ANAF is down, the
 * document is not wrong); a body that is not the documented shape →
 * UnexpectedResponse, with the body attached.
 */
final class AnafClient
{
    /** MF's swagger accepts any body type for /upload; application/xml is what the reference implementations send. */
    public const string UPLOAD_CONTENT_TYPE = 'application/xml';

    private readonly Endpoints $endpoints;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly Environment $environment = Environment::PRODUCTION,
        private readonly Clock $clock = new SystemClock,
        private readonly UblWriter $writer = new UblWriter,
    ) {
        $this->endpoints = $environment->endpoints();
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    /**
     * POST /upload (or /uploadb2c). `$cif` is the CUI the token holds SPV
     * rights for — where the error lands if the seller cannot be read from
     * the XML. Throws UploadRefused when ExecutionStatus is 1.
     */
    public function upload(string $xml, UploadStandard $standard, Cui $cif, UploadOptions $options, #[\SensitiveParameter] string $token): UploadReceipt
    {
        $query = ['standard' => $standard->value, 'cif' => $cif->digits(), ...$options->query()];

        $request = $this->request('POST', $this->endpoints->upload($options->b2c), $query, $token)
            ->withHeader('Content-Type', self::UPLOAD_CONTENT_TYPE)
            ->withBody($this->streams->createStream($xml));

        $response = $this->send($request);
        $header = $this->xmlHeader($response);

        if ((string) $header->getAttribute('ExecutionStatus') === '0' && $header->getAttribute('index_incarcare') !== '') {
            return new UploadReceipt((int) $header->getAttribute('index_incarcare'), $this->anafInstant($header->getAttribute('dateResponse')));
        }

        $messages = $this->errorMessages($header->ownerDocument);
        $body = (string) $response->getBody();

        foreach ($messages as $message) {
            if (($known = AnafResponses::classify($message, $body)) !== null) {
                throw $known;
            }
        }

        throw new UploadRefused($messages === [] ? ['ANAF answered ExecutionStatus 1 without a message.'] : $messages, $body);
    }

    /** Writes the document as UBL and uploads it with the standard and options it implies. */
    public function uploadDocument(Document $document, #[\SensitiveParameter] string $token, ?UploadOptions $options = null, ?Cui $cif = null): UploadReceipt
    {
        $cif ??= $document->seller->cui() ?? throw new \InvalidArgumentException('The seller has no CUI; pass the CUI the token holds SPV rights for.');

        return $this->upload($this->writer->write($document), UploadStandard::for($document->type), $cif, $options ?? UploadOptions::for($document), $token);
    }

    /** GET /stareMesaj. */
    public function status(int $index, #[\SensitiveParameter] string $token): SubmissionStatus
    {
        $response = $this->send($this->request('GET', $this->endpoints->status(), ['id_incarcare' => (string) $index], $token));
        $header = $this->xmlHeader($response);

        if ($header->getAttribute('stare') !== '') {
            $downloadId = $header->getAttribute('id_descarcare');

            return new SubmissionStatus(SubmissionState::fromAnaf($header->getAttribute('stare')), $downloadId === '' ? null : $downloadId);
        }

        $messages = $this->errorMessages($header->ownerDocument);
        $body = (string) $response->getBody();

        foreach ($messages as $message) {
            throw AnafResponses::classify($message, $body) ?? new UnexpectedResponse($message, 200, $body);
        }

        throw new UnexpectedResponse('stareMesaj answered with neither a state nor an error.', 200, $body);
    }

    /** GET /listaMesajeFactura — `$days` is 1..60. */
    public function messages(Cui $cif, int $days, #[\SensitiveParameter] string $token, ?MessageFilter $filter = null): MessageList
    {
        $days = max(1, min(Quotas::LIST_MAX_DAYS, $days));
        $query = ['zile' => (string) $days, 'cif' => $cif->digits()];

        if ($filter !== null) {
            $query['filtru'] = $filter->value;
        }

        $json = $this->listJson($this->send($this->request('GET', $this->endpoints->messages(), $query, $token)));

        return new MessageList($this->messagesFrom($json), (string) ($json['cui'] ?? $cif->digits()), isset($json['titlu']) ? (string) $json['titlu'] : null);
    }

    /** GET /listaMesajePaginatieFactura — the window may not start more than 60 days back nor end in the future. */
    public function messagesBetween(Cui $cif, DateTimeImmutable $from, DateTimeImmutable $to, int $page, #[\SensitiveParameter] string $token, ?MessageFilter $filter = null): MessagePage
    {
        $query = [
            'startTime' => (string) ($from->getTimestamp() * 1000),
            'endTime' => (string) ($to->getTimestamp() * 1000),
            'cif' => $cif->digits(),
            'pagina' => (string) max(1, $page),
        ];

        if ($filter !== null) {
            $query['filtru'] = $filter->value;
        }

        $json = $this->listJson($this->send($this->request('GET', $this->endpoints->messagesPaginated(), $query, $token)));

        return new MessagePage(
            $this->messagesFrom($json),
            (int) ($json['index_pagina_curenta'] ?? $page),
            (int) ($json['numar_total_pagini'] ?? 0),
            (int) ($json['numar_total_inregistrari'] ?? 0),
            (int) ($json['numar_total_inregistrari_per_pagina'] ?? 0),
            (string) ($json['cui'] ?? $cif->digits()),
            isset($json['titlu']) ? (string) $json['titlu'] : null,
        );
    }

    /** GET /descarcare — the ZIP with the payload and MF's signature, unpacked in memory. */
    public function download(string $id, #[\SensitiveParameter] string $token): SignedBundle
    {
        $response = $this->send($this->request('GET', $this->endpoints->download(), ['id' => $id], $token));
        $body = (string) $response->getBody();

        if ($this->looksLikeJson($response, $body)) {
            $json = json_decode($body, true);
            $text = is_array($json) ? (string) ($json['eroare'] ?? $json['message'] ?? $body) : $body;

            throw AnafResponses::classify($text, $body) ?? new UnexpectedResponse($text, 200, $body);
        }

        return SignedBundle::fromZip($body);
    }

    /** POST validare/{FACT1|FCN} on the public host — no credentials, no quota published. */
    public function validate(string $xml, ?DocumentStandard $standard = null): ValidationResult
    {
        $standard ??= DocumentStandard::forXml($xml);

        $request = $this->requests->createRequest('POST', $this->endpoints->validate($standard))
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streams->createStream($xml));

        $response = $this->send($request);
        $json = $this->json($response);

        return AnafResponses::validation($json);
    }

    public function validateDocument(Document $document): ValidationResult
    {
        return $this->validate($this->writer->write($document), DocumentStandard::for($document->type));
    }

    /** POST transformare/{FACT1|FCN}[/DA] — MF's PDF rendering; validates first unless `$skipValidation`. */
    public function render(string $xml, ?DocumentStandard $standard = null, bool $skipValidation = false): PdfDocument
    {
        $standard ??= DocumentStandard::forXml($xml);

        $request = $this->requests->createRequest('POST', $this->endpoints->render($standard, $skipValidation))
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->streams->createStream($xml));

        $response = $this->send($request);
        $body = (string) $response->getBody();

        if ($this->looksLikeJson($response, $body)) {
            $json = json_decode($body, true);

            if (is_array($json) && isset($json['stare'])) {
                throw new RenderRefused(AnafResponses::validation($json), $body);
            }

            throw new UnexpectedResponse('transformare answered JSON without a PDF: '.$body, 200, $body);
        }

        if (! str_starts_with($body, '%PDF')) {
            throw new UnexpectedResponse('transformare did not return a PDF.', $response->getStatusCode(), substr($body, 0, 512));
        }

        return new PdfDocument($body);
    }

    /** POST /api/validate/signature — proves a stored bundle is the one MF signed. */
    public function verifySignature(string $xml, string $signature): bool
    {
        $boundary = 'efactura'.bin2hex(random_bytes(12));
        $body = $this->multipart($boundary, ['file' => ['invoice.xml', $xml], 'signature' => ['signature.xml', $signature]]);

        $request = $this->requests->createRequest('POST', $this->endpoints->verifySignature())
            ->withHeader('Content-Type', 'multipart/form-data; boundary='.$boundary)
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streams->createStream($body));

        $response = $this->send($request, [400]);
        $json = json_decode((string) $response->getBody(), true);
        $message = is_array($json) ? mb_strtolower((string) ($json['msg'] ?? '')) : '';

        if ($response->getStatusCode() === 400) {
            throw new UnexpectedResponse('The signature check refused the files: '.$message, 400, (string) $response->getBody());
        }

        return str_contains($message, 'au fost validate cu succes') && ! str_contains($message, ' nu au putut');
    }

    /** GET /TestOauth/jaxrs/hello — proves a token works. */
    public function hello(#[\SensitiveParameter] string $token, string $name = 'efactura-ro'): string
    {
        $response = $this->send($this->request('GET', $this->endpoints->hello(), ['name' => $name], $token));

        return trim((string) $response->getBody());
    }

    /**
     * @param  array<string, string>  $query
     */
    private function request(string $method, string $url, array $query, #[\SensitiveParameter] string $token): RequestInterface
    {
        $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $this->requests->createRequest($method, $url)
            ->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('Accept', '*/*');
    }

    /**
     * @param  list<int>  $tolerated  statuses the caller wants to see rather than have mapped
     */
    private function send(RequestInterface $request, array $tolerated = []): ResponseInterface
    {
        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportFailure('The request to ANAF did not complete: '.$e->getMessage());
        }

        $status = $response->getStatusCode();

        if ($status < 300 || in_array($status, $tolerated, true)) {
            return $response;
        }

        $body = (string) $response->getBody();
        $text = $this->httpErrorText($body) ?? sprintf('HTTP %d', $status);

        throw match (true) {
            $status === 401, $status === 403 => new Unauthorised($text, $status, $body),
            $status === 429 => new RateLimited($text, $status, $body, $this->retryAfter($response)),
            $status === 404 => new NotFound($text, $status, $body),
            $status >= 500 => new TransportFailure($text, $status, $body),
            default => new AnafException($text, $status, $body),
        };
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $json = json_decode($body, true);

        if (! is_array($json)) {
            throw new UnexpectedResponse('ANAF did not answer with JSON.', $response->getStatusCode(), substr($body, 0, 512));
        }

        return $json;
    }

    /** @return array<string, mixed> */
    private function listJson(ResponseInterface $response): array
    {
        $json = $this->json($response);
        $body = (string) $response->getBody();

        if (isset($json['eroare'])) {
            $text = (string) $json['eroare'];

            if (AnafResponses::isEmptyList($text)) {
                return ['mesaje' => [], 'cui' => $json['cui'] ?? null, 'titlu' => $json['titlu'] ?? null];
            }

            throw AnafResponses::classify($text, $body) ?? new AnafException($text, 200, $body);
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<Message>
     */
    private function messagesFrom(array $json): array
    {
        $messages = [];

        foreach ($json['mesaje'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = MessageType::tryFrom(trim((string) ($row['tip'] ?? '')));

            if ($type === null) {
                throw new UnexpectedResponse(sprintf('Unknown message type "%s" in the list.', (string) ($row['tip'] ?? '')), 200, json_encode($row) ?: null);
            }

            $details = (string) ($row['detalii'] ?? '');

            $messages[] = new Message(
                id: (string) ($row['id'] ?? ''),
                type: $type,
                cif: (string) ($row['cif'] ?? ''),
                requestIndex: isset($row['id_solicitare']) ? (string) $row['id_solicitare'] : null,
                details: $details,
                createdAt: $this->anafInstant((string) ($row['data_creare'] ?? '')),
                sellerCui: isset($row['cif_emitent']) ? (string) $row['cif_emitent'] : $this->cuiFromDetails($details, 'cif_emitent'),
                buyerCui: isset($row['cif_beneficiar']) ? (string) $row['cif_beneficiar'] : $this->cuiFromDetails($details, 'cif_beneficiar'),
            );
        }

        return $messages;
    }

    private function cuiFromDetails(string $details, string $key): ?string
    {
        return preg_match('/'.$key.'=(\d+)/', $details, $m) ? $m[1] : null;
    }

    private function xmlHeader(ResponseInterface $response): \DOMElement
    {
        $body = (string) $response->getBody();
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = $dom->loadXML($body, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $dom->documentElement === null || $dom->documentElement->localName !== 'header') {
            if ($this->looksLikeJson($response, $body)) {
                $json = json_decode($body, true);
                $text = is_array($json) ? (string) ($json['message'] ?? $json['eroare'] ?? $body) : $body;

                throw AnafResponses::classify($text, $body) ?? new UnexpectedResponse($text, $response->getStatusCode(), $body);
            }

            throw new UnexpectedResponse('ANAF did not answer with a <header> element.', $response->getStatusCode(), substr($body, 0, 512));
        }

        return $dom->documentElement;
    }

    /** @return list<string> */
    private function errorMessages(?DOMDocument $dom): array
    {
        if ($dom === null) {
            return [];
        }

        $messages = [];

        foreach ($dom->getElementsByTagName('Errors') as $element) {
            $messages[] = $element->getAttribute('errorMessage');
        }

        return array_values(array_filter($messages, fn ($m) => $m !== ''));
    }

    /** ANAF stamps instants as yyyyMMddHHmm in Bucharest time. */
    private function anafInstant(string $stamp): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('YmdHi', $stamp, new DateTimeZone('Europe/Bucharest'));

        return $parsed === false ? $this->clock->now() : $parsed;
    }

    private function looksLikeJson(ResponseInterface $response, string $body): bool
    {
        $type = strtolower($response->getHeaderLine('Content-Type'));
        $trimmed = ltrim($body);

        return str_contains($type, 'json') || str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }

    private function httpErrorText(string $body): ?string
    {
        $json = json_decode($body, true);

        if (is_array($json)) {
            $text = $json['message'] ?? $json['eroare'] ?? $json['error'] ?? null;

            return $text === null ? null : (string) $text;
        }

        return $body === '' ? null : substr($body, 0, 256);
    }

    private function retryAfter(ResponseInterface $response): ?int
    {
        $value = $response->getHeaderLine('Retry-After');

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $files  name => [filename, content]
     */
    private function multipart(string $boundary, array $files): string
    {
        $body = '';

        foreach ($files as $name => [$filename, $content]) {
            $body .= "--{$boundary}\r\n";
            $body .= sprintf("Content-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\n", $name, $filename);
            $body .= "Content-Type: application/xml\r\n\r\n";
            $body .= $content."\r\n";
        }

        return $body."--{$boundary}--\r\n";
    }
}
