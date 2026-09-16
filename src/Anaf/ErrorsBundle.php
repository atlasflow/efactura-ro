<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

use DOMDocument;

/** Reads the error list ANAF puts in the bundle of a NOK submission. */
final class ErrorsBundle
{
    /** @return list<string> */
    public static function messages(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [trim($xml)];
        }

        $messages = [];

        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element->hasAttribute('errorMessage')) {
                $messages[] = $element->getAttribute('errorMessage');
            }
        }

        return $messages === [] ? [trim($dom->textContent)] : $messages;
    }
}
