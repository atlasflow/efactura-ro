<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\OAuth;

use AtlasFlow\EFacturaRo\Anaf\Endpoints;

/**
 * The URL the certificate holder opens to authorise an application. ANAF
 * asks for `response_type=code` and `token_content_type=jwt` on the query;
 * `state` is added only when given (ANAF's own instructions leave it
 * empty, and whether it round-trips is an open item — the caller must not
 * depend on it to correlate the callback).
 */
final class AuthorizationUrl
{
    public static function build(string $clientId, string $redirectUri, ?string $state = null): string
    {
        $query = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'token_content_type' => 'jwt',
        ];

        if ($state !== null && $state !== '') {
            $query['state'] = $state;
        }

        return Endpoints::OAUTH_BASE.'/authorize?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
