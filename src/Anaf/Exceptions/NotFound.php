<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\Exceptions;

/** "Nu exista factura cu id_incarcare=…", "Pentru id=… nu exista inregistrata nici o factura", or an HTTP 404. */
final class NotFound extends AnafException {}
