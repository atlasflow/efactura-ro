<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

/** BT-158: an item classification such as a CPV ("STI") or CN ("TSP") code; the list id is mandatory (BR-65). */
final readonly class Classification
{
    public function __construct(
        public string $code,
        public string $listId,
    ) {}
}
