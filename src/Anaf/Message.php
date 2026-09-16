<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

use DateTimeImmutable;

/**
 * One row of /listaMesajeFactura. `id` downloads the bundle; `requestIndex`
 * is the upload index the row refers to (`id_solicitare`). The seller and
 * buyer CUIs are parsed from `detalii` when ANAF does not send them as
 * fields.
 */
final readonly class Message
{
    public function __construct(
        public string $id,
        public MessageType $type,
        public string $cif,
        public ?string $requestIndex,
        public string $details,
        public DateTimeImmutable $createdAt,
        public ?string $sellerCui = null,
        public ?string $buyerCui = null,
    ) {}
}
