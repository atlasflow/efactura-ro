<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

use AtlasFlow\EFacturaRo\Anaf\Exceptions\AnafException;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\NotFound;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\RateLimited;
use AtlasFlow\EFacturaRo\Anaf\Exceptions\Unauthorised;
use AtlasFlow\EFacturaRo\Validation\ValidationError;
use AtlasFlow\EFacturaRo\Validation\ValidationResult;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/**
 * The shapes ANAF answers with, turned into kernel types. ANAF reports many
 * failures inside an HTTP 200 — as an `eroare` field in JSON or an
 * `Errors/@errorMessage` in XML — so the classification is by text, with the
 * phrases taken from MF's swagger examples (read 2026-09-16).
 */
final class AnafResponses
{
    /** Maps an `eroare` / `errorMessage` text to the exception it deserves, or null when it is a plain error. */
    public static function classify(string $text, ?string $body = null): ?AnafException
    {
        $lower = mb_strtolower($text);

        if (str_contains($lower, 's-au facut deja') || str_contains($lower, 'limita')) {
            $quota = match (true) {
                str_contains($lower, 'descarcari') => 'downloads-per-message-per-day',
                str_contains($lower, 'interogari de lista') => 'list-per-day',
                str_contains($lower, 'interogari') => 'status-per-message-per-day',
                default => null,
            };

            return new RateLimited($text, 200, $body, null, $quota);
        }

        if (str_contains($lower, 'nu aveti drept') || str_contains($lower, 'nu aveti dreptul') || str_contains($lower, 'nu exista niciun cif')) {
            return new Unauthorised($text, 200, $body);
        }

        if (str_contains($lower, 'nu exista factura') || str_contains($lower, 'nu exista inregistrata nici o factura')) {
            return new NotFound($text, 200, $body);
        }

        return null;
    }

    /** True for the "no messages" answer, which is an empty list rather than an error. */
    public static function isEmptyList(string $text): bool
    {
        return str_starts_with(mb_strtolower($text), 'nu exista mesaje');
    }

    /**
     * Parses the JSON of /validare and /transformare into a ValidationResult.
     * Messages come structured ("tipAssert=FailedAssert; codEroare=BR-CO-26;
     * localizareEroare=/Invoice/…; textEroare=…; expresieValidata=…") or in
     * the older free form ("E: validari globale SCHEMATRON eroare: [BR-CO-09]-…").
     *
     * @param  array<string, mixed>  $json
     */
    public static function validation(array $json): ValidationResult
    {
        $traceId = isset($json['trace_id']) ? (string) $json['trace_id'] : null;

        if (($json['stare'] ?? null) === 'ok') {
            return ValidationResult::ok($traceId);
        }

        $errors = [];

        foreach ($json['Messages'] ?? [] as $entry) {
            $text = is_array($entry) ? (string) ($entry['message'] ?? '') : (string) $entry;
            $errors[] = self::validationError($text);
        }

        if ($errors === []) {
            $errors[] = new ValidationError('ANAF', '/', 'ANAF answered "nok" without messages.', ValidationSource::ANAF);
        }

        return ValidationResult::failed($errors, $traceId);
    }

    public static function validationError(string $text): ValidationError
    {
        if (str_contains($text, 'codEroare=')) {
            $fields = [];

            foreach (explode('; ', $text) as $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
                $fields[trim($key)] = trim($value);
            }

            return new ValidationError(
                ($fields['codEroare'] ?? '') ?: 'ANAF',
                ($fields['localizareEroare'] ?? '') ?: '/',
                ($fields['textEroare'] ?? '') ?: $text,
                ValidationSource::ANAF,
            );
        }

        if (preg_match('/\[([A-Z0-9_-]+)\]-?(.*)$/s', $text, $m)) {
            return new ValidationError($m[1], '/', trim($m[2]), ValidationSource::ANAF);
        }

        if (preg_match('/eroare regula:\s*([A-Za-z0-9_]+):\s*(.*)$/s', $text, $m)) {
            return new ValidationError($m[1], '/', trim($m[2]), ValidationSource::ANAF);
        }

        return new ValidationError('ANAF', '/', trim($text), ValidationSource::ANAF);
    }
}
