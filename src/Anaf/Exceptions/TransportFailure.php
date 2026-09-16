<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\Exceptions;

/** The PSR-18 client could not complete the request, or ANAF answered 5xx — ANAF is unavailable, not the document wrong. */
final class TransportFailure extends AnafException {}
