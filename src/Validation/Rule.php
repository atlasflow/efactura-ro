<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation;

use AtlasFlow\EFacturaRo\Document\Document;

/** One rule over the model. Implementations live in Validation\Rules, one per file, named for what they check. */
interface Rule
{
    /** @return list<ValidationError> */
    public function check(Document $document): array;
}
