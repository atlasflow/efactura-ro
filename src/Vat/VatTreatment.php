<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Vat;

/**
 * The neutral, jurisdiction-free way a consumer describes how a sale is
 * taxed. A host application persists one of these on the buyer or the line
 * and lets RomanianVatMapping turn it into the codes ANAF wants.
 */
enum VatTreatment: string
{
    /** VAT charged at a positive rate. */
    case STANDARD = 'standard';

    /** A domestic supply taxed at 0 % (category Z). */
    case ZERO_RATED = 'zero_rated';

    /** Goods shipped to a VAT-registered buyer in another EU member state. */
    case INTRA_EU_SUPPLY = 'intra_eu_supply';

    /** The buyer accounts for the VAT (taxare inversă). */
    case REVERSE_CHARGE = 'reverse_charge';

    /** Exempt under a specific article; the caller names the article. */
    case EXEMPT = 'exempt';

    /** Goods leaving the EU. */
    case EXPORT = 'export';

    /** Outside the scope of VAT altogether. */
    case OUT_OF_SCOPE = 'out_of_scope';
}
