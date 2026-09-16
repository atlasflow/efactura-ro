<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

use AtlasFlow\EFacturaRo\Document\DocumentType;

/** The `{standard}` path segment of the public validare and transformare endpoints. */
enum DocumentStandard: string
{
    /** A UBL Invoice (380, 384, 389, 751). */
    case INVOICE = 'FACT1';

    /** A UBL CreditNote (381). */
    case CREDIT_NOTE = 'FCN';

    public static function for(DocumentType $type): self
    {
        return $type->isCreditNote() ? self::CREDIT_NOTE : self::INVOICE;
    }

    public static function forXml(string $xml): self
    {
        return preg_match('/<(\w+:)?CreditNote[\s>]/', substr($xml, 0, 4096)) === 1 ? self::CREDIT_NOTE : self::INVOICE;
    }
}
