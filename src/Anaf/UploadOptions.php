<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

use AtlasFlow\EFacturaRo\Document\Document;

/**
 * The upload flags. Each maps to a query parameter that ANAF accepts only
 * with the value DA: `extern` (buyer outside Romania, no CUI), `autofactura`
 * (the buyer issues on the seller's behalf, type 389), `executare` (filed by
 * an enforcement body). `b2c` picks /uploadb2c, mandatory for consumers
 * since 2025-03-31.
 */
final readonly class UploadOptions
{
    public function __construct(
        public bool $b2c = false,
        public bool $foreignBuyer = false,
        public bool $selfBilled = false,
        public bool $enforcement = false,
    ) {}

    /** Derive the flags a Document itself implies; `enforcement` is never implied. */
    public static function for(Document $document): self
    {
        return new self(
            b2c: $document->isB2C(),
            foreignBuyer: $document->hasForeignBuyer(),
            selfBilled: $document->type->value === '389',
        );
    }

    /** @return array<string, string> */
    public function query(): array
    {
        $query = [];

        if ($this->foreignBuyer) {
            $query['extern'] = 'DA';
        }

        if ($this->selfBilled) {
            $query['autofactura'] = 'DA';
        }

        if ($this->enforcement) {
            $query['executare'] = 'DA';
        }

        return $query;
    }
}
