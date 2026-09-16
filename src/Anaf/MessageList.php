<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

/** The answer of /listaMesajeFactura: an empty list when ANAF says "Nu exista mesaje". */
final readonly class MessageList
{
    /**
     * @param  list<Message>  $messages
     */
    public function __construct(
        public array $messages,
        public string $cui,
        public ?string $title = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->messages === [];
    }
}
