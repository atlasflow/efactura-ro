<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation;

/**
 * A hook for consumers who run MF's ro16931-ubl schematron locally (with
 * Saxon, ph-schematron or similar). The kernel does not ship a processor;
 * the default is NullSchematronValidator and ANAF's validare endpoint is
 * the authoritative check.
 */
interface SchematronValidator
{
    public function validate(string $xml): ValidationResult;
}
