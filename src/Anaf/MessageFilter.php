<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

/** The optional `filtru` of the list endpoints. */
enum MessageFilter: string
{
    /** Errors on our invoices (ERORI FACTURA). */
    case ERRORS = 'E';

    /** Our invoices, sent (FACTURA TRIMISA). */
    case SENT = 'T';

    /** Invoices received (FACTURA PRIMITA). */
    case RECEIVED = 'P';

    /** Buyer messages (RASP). */
    case BUYER_MESSAGES = 'R';
}
