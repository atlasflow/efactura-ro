<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation;

/** Which layer produced an error — the caller can tell a structural problem from a fiscal one. */
enum ValidationSource: string
{
    /** libxml against the OASIS UBL 2.1 schema. */
    case XSD = 'xsd';

    /** The EN 16931 calculation rules (BR-CO-10..17, BR-S-08/09 and their siblings). */
    case ARITHMETIC = 'arithmetic';

    /** Party identifiers: CUI check digits, CNP structure, VAT prefixes. */
    case IDENTITY = 'identity';

    /** The EN 16931 semantic rules that are not arithmetic (categories, reasons, presence). */
    case EN16931 = 'en16931';

    /** The Romanian BR-RO-* rules. */
    case CIUS_RO = 'cius-ro';

    /** An injected Schematron processor. */
    case SCHEMATRON = 'schematron';

    /** ANAF's public validare endpoint. */
    case ANAF = 'anaf';
}
