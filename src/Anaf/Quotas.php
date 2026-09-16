<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

/**
 * MF's published call limits, for the consumer's rate limiter. Source:
 * https://mfinante.gov.ro/static/10/eFactura/limiteApeluriAPI.txt (read
 * 2026-09-16). MF says the limits may change and that ignoring limit
 * errors repeatedly can get the user — and the application — blocked.
 */
final class Quotas
{
    /** Every method: calls per minute. */
    public const int CALLS_PER_MINUTE = 1000;

    /** /upload: RASP files per day per CUI. Invoice uploads are unlimited. */
    public const int RASP_UPLOADS_PER_DAY = 1000;

    /** /stareMesaj: queries per message per day. */
    public const int STATUS_PER_MESSAGE_PER_DAY = 100;

    /** /listaMesajeFactura: queries per day per CUI. */
    public const int LIST_SIMPLE_PER_DAY = 1500;

    /** /listaMesajePaginatieFactura: queries per day per CUI. */
    public const int LIST_PAGINATED_PER_DAY = 100_000;

    /** /descarcare: downloads per message per day. */
    public const int DOWNLOADS_PER_MESSAGE_PER_DAY = 10;

    /** /listaMesajeFactura: the widest `zile` window ANAF accepts. */
    public const int LIST_MAX_DAYS = 60;
}
