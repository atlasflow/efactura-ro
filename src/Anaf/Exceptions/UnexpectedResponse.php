<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\Exceptions;

/** A 200 whose body is not the shape MF documents; the body is attached for the log. */
final class UnexpectedResponse extends AnafException {}
