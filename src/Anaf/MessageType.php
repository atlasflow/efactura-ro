<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

/** The `tip` of a row in the message list. */
enum MessageType: string
{
    /** Our invoice, validated: the bundle holds the invoice and MF's signature. */
    case INVOICE_SENT = 'FACTURA TRIMISA';

    /** An invoice someone sent us as buyer. */
    case INVOICE_RECEIVED = 'FACTURA PRIMITA';

    /** Our invoice was rejected: the bundle holds the error list and MF's signature. */
    case INVOICE_ERRORS = 'ERORI FACTURA';

    /** A RASP message a buyer sent about our invoice. */
    case BUYER_MESSAGE_RECEIVED = 'MESAJ CUMPARATOR PRIMIT';

    /** A RASP message we sent as buyer. */
    case BUYER_MESSAGE_SENT = 'MESAJ CUMPARATOR TRANSMIS';

    public function isBuyerMessage(): bool
    {
        return $this === self::BUYER_MESSAGE_RECEIVED || $this === self::BUYER_MESSAGE_SENT;
    }
}
