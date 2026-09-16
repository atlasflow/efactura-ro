<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\Exceptions;

/** HTTP 401/403, an expired token, or "Nu aveti drept in SPV pentru CIF=…" inside a 200. */
final class Unauthorised extends AnafException {}
