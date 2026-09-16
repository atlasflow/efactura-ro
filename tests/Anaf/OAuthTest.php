<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Anaf\Exceptions\Unauthorised;
use AtlasFlow\EFacturaRo\Anaf\OAuth\AuthorizationUrl;
use AtlasFlow\EFacturaRo\Anaf\OAuth\JwtClaims;
use AtlasFlow\EFacturaRo\Anaf\OAuth\OAuthClient;
use AtlasFlow\EFacturaRo\Anaf\OAuth\TokenPair;
use AtlasFlow\EFacturaRo\Support\FrozenClock;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;

function jwt(array $claims): string
{
    $encode = fn (array $a) => rtrim(strtr(base64_encode((string) json_encode($a)), '+/', '-_'), '=');

    return $encode(['alg' => 'RS512', 'kid' => 'anaf_2023_2024']).'.'.$encode($claims).'.sig';
}

function oauth(): array
{
    $http = new MockClient;
    $factory = new Psr17Factory;
    $client = new OAuthClient($http, $factory, $factory, 'app-id', 'app-secret', FrozenClock::at('2026-09-16 10:00:00 UTC'));

    return [$http, $client];
}

it('builds the authorisation URL ANAF expects, with state only when given', function () {
    $url = AuthorizationUrl::build('app-id', 'https://core.example/anaf/callback');

    expect($url)->toBe('https://logincert.anaf.ro/anaf-oauth2/v1/authorize?response_type=code&client_id=app-id&redirect_uri=https%3A%2F%2Fcore.example%2Fanaf%2Fcallback&token_content_type=jwt')
        ->and(AuthorizationUrl::build('app-id', 'https://x', 'nonce-1'))->toEndWith('&state=nonce-1');
});

it('exchanges a code with Basic auth and a form body', function () {
    [$http, $client] = oauth();
    $now = strtotime('2026-09-16 10:00:00 UTC');
    $access = jwt(['iss' => 'https://logincert.anaf.ro', 'iat' => $now, 'exp' => $now + 90 * 86400, 'serial' => 'ABC123', 'scope' => 'clientappid info issuer role serial']);
    $refresh = jwt(['iat' => $now, 'exp' => $now + 365 * 86400]);
    $http->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => $access, 'refresh_token' => $refresh, 'token_type' => 'bearer', 'expires_in' => 7775999])));

    $pair = $client->exchange('the-code', 'https://core.example/anaf/callback');
    $request = $http->getLastRequest();

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://logincert.anaf.ro/anaf-oauth2/v1/token')
        ->and($request->getHeaderLine('Authorization'))->toBe('Basic '.base64_encode('app-id:app-secret'))
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded')
        ->and((string) $request->getBody())->toBe('grant_type=authorization_code&code=the-code&redirect_uri=https%3A%2F%2Fcore.example%2Fanaf%2Fcallback&token_content_type=jwt')
        ->and($pair->accessToken)->toBe($access)
        ->and($pair->certificateSerial)->toBe('ABC123')
        ->and($pair->coveredCuis)->toBe([])
        ->and($pair->accessExpiresAt->format('Y-m-d'))->toBe('2026-12-15')
        ->and($pair->refreshExpiresAt->format('Y-m-d'))->toBe('2027-09-16')
        ->and($pair->issuedAt->getTimestamp())->toBe($now);
});

it('refreshes with the refresh token and rotates the pair', function () {
    [$http, $client] = oauth();
    $http->addResponse(new Response(200, ['Content-Type' => 'application/json'], (string) file_get_contents(__DIR__.'/../fixtures/anaf/token.json')));

    $old = new TokenPair('old-access', 'old-refresh', new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2027-09-01'), new DateTimeImmutable('2026-09-01'));
    $pair = $client->refresh($old);

    expect((string) $http->getLastRequest()->getBody())->toBe('grant_type=refresh_token&refresh_token=old-refresh&token_content_type=jwt')
        ->and($pair->accessToken)->toBe('ACCESS_JWT')
        ->and($pair->refreshToken)->toBe('REFRESH_JWT');
});

it('falls back to expires_in and ANAF lifetimes when the tokens are not JWTs', function () {
    $pair = TokenPair::fromResponse(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600], new DateTimeImmutable('2026-09-16 10:00:00 UTC'));

    expect($pair->accessExpiresAt->format('Y-m-d H:i'))->toBe('2026-09-16 11:00')
        ->and($pair->refreshExpiresAt->format('Y-m-d'))->toBe('2027-09-16')
        ->and($pair->accessExpiresWithin(new DateTimeImmutable('2026-09-16 10:30:00 UTC'), 7 * 86400))->toBeTrue()
        ->and($pair->refreshExpired(new DateTimeImmutable('2027-09-17')))->toBeTrue();
});

it('reads JWT claims without verifying the signature and refuses non-JWTs', function () {
    $claims = JwtClaims::parse(jwt(['exp' => 1800000000, 'serial' => '42', 'cif' => '12345674']));

    expect($claims->expiresAt()?->getTimestamp())->toBe(1800000000)
        ->and($claims->certificateSerial())->toBe('42')
        ->and($claims->coveredCuis())->toBe(['12345674'])
        ->and($claims->get('missing'))->toBeNull()
        ->and(fn () => JwtClaims::parse('nope'))->toThrow(InvalidArgumentException::class);
});

it('maps a 400/401 from the token endpoint to Unauthorised with ANAF\'s reason', function () {
    [$http, $client] = oauth();
    $http->addResponse(new Response(400, ['Content-Type' => 'application/json'], '{"error":"invalid_grant","error_description":"Authorization code expired"}'));

    $client->exchange('stale', 'https://x');
})->throws(Unauthorised::class, 'Authorization code expired');
