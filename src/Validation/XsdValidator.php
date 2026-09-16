<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation;

use DOMDocument;

/** Validates UBL XML against the bundled OASIS UBL 2.1 Invoice or CreditNote schema. */
final class XsdValidator
{
    private const string SCHEMA_DIR = __DIR__.'/../../resources/xsd/maindoc/';

    public function validate(string $xml): ValidationResult
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument;

            if (! $dom->loadXML($xml, LIBXML_NONET)) {
                return $this->fromLibxml('/', 'XML-PARSE');
            }

            $root = $dom->documentElement?->localName;
            $schema = match ($root) {
                'Invoice' => 'UBL-Invoice-2.1.xsd',
                'CreditNote' => 'UBL-CreditNote-2.1.xsd',
                default => null,
            };

            if ($schema === null) {
                return ValidationResult::failed([
                    new ValidationError('XSD-ROOT', '/', sprintf('Expected a UBL Invoice or CreditNote root element, found "%s".', $root ?? ''), ValidationSource::XSD),
                ]);
            }

            if ($dom->schemaValidate(self::SCHEMA_DIR.$schema)) {
                return ValidationResult::ok();
            }

            return $this->fromLibxml('/'.$root, 'XSD');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function fromLibxml(string $path, string $code): ValidationResult
    {
        $errors = [];

        foreach (libxml_get_errors() as $error) {
            $errors[] = new ValidationError(
                $code,
                $path.($error->line > 0 ? sprintf(' (line %d)', $error->line) : ''),
                trim($error->message),
                ValidationSource::XSD,
            );
        }

        return ValidationResult::failed($errors === [] ? [new ValidationError($code, $path, 'The document did not validate.', ValidationSource::XSD)] : $errors);
    }
}
