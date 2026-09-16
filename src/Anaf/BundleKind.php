<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

enum BundleKind: string
{
    /** A validated invoice or credit note with MF's signature. */
    case INVOICE = 'invoice';

    /** The error list of a rejected upload with MF's signature. */
    case ERRORS = 'errors';

    /** A buyer message. */
    case RASP = 'rasp';
}
