<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\OAuth;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The payload of an ANAF JWT, decoded without verifying the signature —
 * ANAF verifies on use; the kernel only reads the claims to know when the
 * token expires and which certificate it was issued to.
 *
 * Known claims (ANAF's OAuth procedure, corroborated by third-party probes):
 * `iss`, `iat`, `nbf`, `exp`, `token_type`, `scope`, `role`, `clientappid`,
 * `serial` (the certificate serial). No claim naming the CUIs the holder
 * has SPV rights for has been confirmed, so coveredCuis() returns [] and a
 * consumer must treat the authorisation as covering exactly the CUI it was
 * started for.
 */
final readonly class JwtClaims
{
    /**
     * @param  array<string, mixed>  $claims
     */
    private function __construct(public array $claims) {}

    public static function parse(string $jwt): self
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Not a JWT: expected three dot-separated segments.');
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/').str_repeat('=', (4 - strlen($parts[1]) % 4) % 4), true);
        $claims = $payload === false ? null : json_decode($payload, true);

        if (! is_array($claims)) {
            throw new InvalidArgumentException('Not a JWT: the payload is not a JSON object.');
        }

        return new self($claims);
    }

    public function get(string $name): mixed
    {
        return $this->claims[$name] ?? null;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->instant('exp');
    }

    public function issuedAt(): ?DateTimeImmutable
    {
        return $this->instant('iat');
    }

    public function certificateSerial(): ?string
    {
        foreach (['serial', 'serialNumber', 'certificate_serial'] as $name) {
            if (isset($this->claims[$name]) && is_scalar($this->claims[$name])) {
                return (string) $this->claims[$name];
            }
        }

        return null;
    }

    /** @return list<string> */
    public function coveredCuis(): array
    {
        foreach (['cuis', 'cifs', 'cui', 'cif'] as $name) {
            $value = $this->claims[$name] ?? null;

            if (is_array($value)) {
                return array_values(array_map('strval', array_filter($value, 'is_scalar')));
            }

            if (is_scalar($value) && (string) $value !== '') {
                return [(string) $value];
            }
        }

        return [];
    }

    private function instant(string $name): ?DateTimeImmutable
    {
        $value = $this->claims[$name] ?? null;

        return is_numeric($value) ? new DateTimeImmutable('@'.(int) $value) : null;
    }
}
