<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

use AtlasFlow\EFacturaRo\Document\DocumentType;

/** The `standard` query parameter of /upload and /uploadb2c. */
enum UploadStandard: string
{
    /** A UBL Invoice. */
    case UBL = 'UBL';

    /** A UBL CreditNote. */
    case CN = 'CN';

    /** UN/CEFACT Cross Industry Invoice — transported, never written by this package. */
    case CII = 'CII';

    /** A buyer → seller message. */
    case RASP = 'RASP';

    public static function for(DocumentType $type): self
    {
        return $type->isCreditNote() ? self::CN : self::UBL;
    }
}
