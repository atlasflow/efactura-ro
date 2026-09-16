<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation;

final class NullSchematronValidator implements SchematronValidator
{
    public function validate(string $xml): ValidationResult
    {
        return ValidationResult::ok();
    }
}
