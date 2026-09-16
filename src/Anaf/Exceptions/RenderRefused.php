<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf\Exceptions;

use AtlasFlow\EFacturaRo\Validation\ValidationResult;

/** /transformare validated the XML first and found errors; the PDF was not produced. */
final class RenderRefused extends AnafException
{
    public function __construct(public readonly ValidationResult $validation, ?string $body = null)
    {
        parent::__construct('ANAF would not render the document: '.implode('; ', $validation->codes()), 200, $body);
    }
}
