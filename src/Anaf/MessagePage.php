<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

/** One page of /listaMesajePaginatieFactura. */
final readonly class MessagePage
{
    /**
     * @param  list<Message>  $messages
     */
    public function __construct(
        public array $messages,
        public int $page,
        public int $totalPages,
        public int $totalRecords,
        public int $perPage,
        public string $cui,
        public ?string $title = null,
    ) {}

    public function hasMore(): bool
    {
        return $this->page < $this->totalPages;
    }
}
