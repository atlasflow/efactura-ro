<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\OAuth;

use DateTimeImmutable;

/**
 * What the token endpoint hands back, with the expiry instants read from
 * the JWTs (falling back to `expires_in` and ANAF's documented lifetimes:
 * 90 days for the access token, 365 for the refresh token). Refreshing
 * rotates both; persist the whole pair every time.
 *
 * `coveredCuis` is empty until ANAF's JWT is confirmed to carry the list;
 * until then an authorisation covers exactly the CUI it was started for.
 */
final readonly class TokenPair
{
    public const int ACCESS_LIFETIME_SECONDS = 90 * 86400;

    public const int REFRESH_LIFETIME_SECONDS = 365 * 86400;

    /**
     * @param  list<string>  $coveredCuis
     */
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public DateTimeImmutable $accessExpiresAt,
        public DateTimeImmutable $refreshExpiresAt,
        public DateTimeImmutable $issuedAt,
        public ?string $certificateSerial = null,
        public array $coveredCuis = [],
    ) {}

    /**
     * @param  array<string, mixed>  $response  the decoded token endpoint JSON
     */
    public static function fromResponse(array $response, DateTimeImmutable $now): self
    {
        $access = (string) ($response['access_token'] ?? '');
        $refresh = (string) ($response['refresh_token'] ?? '');

        $accessClaims = self::claimsOf($access);
        $refreshClaims = self::claimsOf($refresh);

        $expiresIn = isset($response['expires_in']) && is_numeric($response['expires_in']) ? (int) $response['expires_in'] : null;

        return new self(
            accessToken: $access,
            refreshToken: $refresh,
            accessExpiresAt: $accessClaims?->expiresAt() ?? $now->modify(sprintf('+%d seconds', $expiresIn ?? self::ACCESS_LIFETIME_SECONDS)),
            refreshExpiresAt: $refreshClaims?->expiresAt() ?? $now->modify(sprintf('+%d seconds', self::REFRESH_LIFETIME_SECONDS)),
            issuedAt: $accessClaims?->issuedAt() ?? $now,
            certificateSerial: $accessClaims?->certificateSerial(),
            coveredCuis: $accessClaims?->coveredCuis() ?? [],
        );
    }

    public function accessExpiresWithin(DateTimeImmutable $now, int $seconds): bool
    {
        return $this->accessExpiresAt->getTimestamp() - $now->getTimestamp() <= $seconds;
    }

    public function refreshExpired(DateTimeImmutable $now): bool
    {
        return $this->refreshExpiresAt <= $now;
    }

    private static function claimsOf(string $jwt): ?JwtClaims
    {
        try {
            return $jwt === '' ? null : JwtClaims::parse($jwt);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
