<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

/**
 * Where each call goes. Sources: MF's swagger pages under
 * https://mfinante.gov.ro/static/10/eFactura/ (upload, staremesaj,
 * listamesaje, descarcare, validare, xmltopdf, validaresemnatura) and
 * ANAF's OAuth registration procedure, read 2026-09-16.
 */
final readonly class Endpoints
{
    public const string OAUTH_BASE = 'https://logincert.anaf.ro/anaf-oauth2/v1';

    public const string PUBLIC_BASE = 'https://webservicesp.anaf.ro';

    public const string API_BASE = 'https://api.anaf.ro';

    public function __construct(private Environment $environment) {}

    /** The authenticated e-Factura REST root for this environment. */
    public function rest(): string
    {
        return sprintf('%s/%s/FCTEL/rest', self::API_BASE, $this->environment->value);
    }

    public function upload(bool $b2c): string
    {
        return $this->rest().($b2c ? '/uploadb2c' : '/upload');
    }

    public function status(): string
    {
        return $this->rest().'/stareMesaj';
    }

    public function messages(): string
    {
        return $this->rest().'/listaMesajeFactura';
    }

    public function messagesPaginated(): string
    {
        return $this->rest().'/listaMesajePaginatieFactura';
    }

    public function download(): string
    {
        return $this->rest().'/descarcare';
    }

    /** Public, no credentials. Always production: there is no test validator. */
    public function validate(DocumentStandard $standard): string
    {
        return sprintf('%s/prod/FCTEL/rest/validare/%s', self::PUBLIC_BASE, $standard->value);
    }

    public function render(DocumentStandard $standard, bool $skipValidation): string
    {
        return sprintf('%s/prod/FCTEL/rest/transformare/%s%s', self::PUBLIC_BASE, $standard->value, $skipValidation ? '/DA' : '');
    }

    public function verifySignature(): string
    {
        return self::PUBLIC_BASE.'/api/validate/signature';
    }

    public function hello(): string
    {
        return self::API_BASE.'/TestOauth/jaxrs/hello';
    }

    public function authorize(): string
    {
        return self::OAUTH_BASE.'/authorize';
    }

    public function token(): string
    {
        return self::OAUTH_BASE.'/token';
    }
}
