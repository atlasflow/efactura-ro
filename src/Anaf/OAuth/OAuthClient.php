<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\OAuth;

use AtlasFlow\EFacturaRo\Anaf\Endpoints;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\TransportFailure;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\Unauthorised;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\UnexpectedResponse;
use AtlasFlow\EFacturaRo\Support\Clock;
use AtlasFlow\EFacturaRo\Support\SystemClock;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The two calls to logincert.anaf.ro: exchange an authorisation code (within
 * ANAF's 60-second window) and refresh a pair. Both use HTTP Basic with the
 * application's client id and secret and a form body; neither needs the
 * certificate, which is why a server can keep itself authorised for the
 * full refresh lifetime. The OAuth host is the same for TEST and PRODUCTION.
 */
final class OAuthClient
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
        private readonly Clock $clock = new SystemClock,
    ) {}

    public function exchange(string $code, string $redirectUri): TokenPair
    {
        return $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'token_content_type' => 'jwt',
        ]);
    }

    public function refresh(TokenPair $pair): TokenPair
    {
        return $this->token([
            'grant_type' => 'refresh_token',
            'refresh_token' => $pair->refreshToken,
            'token_content_type' => 'jwt',
        ]);
    }

    /**
     * @param  array<string, string>  $form
     */
    private function token(array $form): TokenPair
    {
        $request = $this->requests->createRequest('POST', Endpoints::OAUTH_BASE.'/token')
            ->withHeader('Authorization', 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streams->createStream(http_build_query($form, '', '&', PHP_QUERY_RFC3986)));

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportFailure('The token request did not complete: '.$e->getMessage());
        }

        return $this->pairFrom($response);
    }

    private function pairFrom(ResponseInterface $response): TokenPair
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status === 401 || $status === 403 || $status === 400) {
            throw new Unauthorised(sprintf('ANAF refused the token request (%d): %s', $status, $this->errorText($body)), $status, $body);
        }

        if ($status >= 500) {
            throw new TransportFailure(sprintf('ANAF token endpoint answered %d.', $status), $status, $body);
        }

        $json = json_decode($body, true);

        if (! is_array($json) || ! isset($json['access_token'], $json['refresh_token'])) {
            throw new UnexpectedResponse('The token response carries no access_token and refresh_token.', $status, $body);
        }

        return TokenPair::fromResponse($json, $this->clock->now());
    }

    private function errorText(string $body): string
    {
        $json = json_decode($body, true);

        if (is_array($json)) {
            return (string) ($json['error_description'] ?? $json['error'] ?? $json['message'] ?? $body);
        }

        return $body;
    }
}
