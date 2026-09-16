<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

/**
 * ANAF's two OAuth environments. TEST is not a mock: same validation, real
 * tokens, nothing delivered to a real buyer's SPV.
 */
enum Environment: string
{
    case TEST = 'test';

    case PRODUCTION = 'prod';

    public function endpoints(): Endpoints
    {
        return new Endpoints($this);
    }
}
