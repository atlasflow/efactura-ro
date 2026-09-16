<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document\Rasp;

/**
 * What a buyer can say back about a received invoice. The RASP schema MF
 * publishes is not reachable from here; these values are the ones its
 * examples show and are verified against the XSD when it is fetched
 * (see the spec's open questions).
 */
enum RaspType: string
{
    case ACCEPTED = 'ACCEPTAT';

    case REJECTED = 'REFUZAT';

    case COMMENT = 'COMENTARIU';
}
