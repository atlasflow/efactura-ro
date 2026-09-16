<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation;

/**
 * One failed check. `code` is the rule id as the schematron names it
 * (BR-CO-10, BR-RO-100…) or ANAF's `codEroare`; `path` is a UBL XPath when
 * the error came from XML and a dotted DTO path (`lines[2].netAmount`)
 * when it came from the model.
 */
final readonly class ValidationError
{
    public function __construct(
        public string $code,
        public string $path,
        public string $message,
        public ValidationSource $source,
    ) {}

    public function __toString(): string
    {
        return sprintf('[%s] %s at %s: %s', $this->source->value, $this->code, $this->path, $this->message);
    }
}
