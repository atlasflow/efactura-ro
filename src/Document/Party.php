<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

use AtlasFlow\EFacturaRo\Support\Cnp;
use AtlasFlow\EFacturaRo\Support\Cui;
use AtlasFlow\EFacturaRo\Support\TaxIdentifier;

/**
 * The seller (BG-4), the buyer (BG-7) or the payee (BG-10).
 *
 * `taxIdentifier` is a Cui, a Cnp or a ForeignIdentifier. `vatRegistered`
 * decides whether the writer puts the identifier in PartyTaxScheme with the
 * "RO" prefix (a VAT identifier, BT-31/BT-48) or in PartyLegalEntity as a
 * bare registration identifier (BT-30/BT-47). A consumer buyer is built with
 * Party::consumer(); when it has no CNP the writer emits the CIUS-RO
 * placeholder 0000000000000.
 */
final readonly class Party
{
    public function __construct(
        public string $name,
        public Address $address,
        public ?TaxIdentifier $taxIdentifier = null,
        public bool $vatRegistered = false,
        public ?string $registrationNumber = null,
        public ?string $legalForm = null,
        public ?string $tradingName = null,
        public ?Contact $contact = null,
        public ?Endpoint $endpoint = null,
        public ?string $identifier = null,
        public bool $isConsumer = false,
    ) {}

    /** A natural person buying for private use (B2C). */
    public static function consumer(string $name, Address $address, ?Cnp $cnp = null, ?Contact $contact = null): self
    {
        return new self(
            name: $name,
            address: $address,
            taxIdentifier: $cnp,
            contact: $contact,
            isConsumer: true,
        );
    }

    public function cui(): ?Cui
    {
        return $this->taxIdentifier instanceof Cui ? $this->taxIdentifier : null;
    }

    public function cnp(): ?Cnp
    {
        return $this->taxIdentifier instanceof Cnp ? $this->taxIdentifier : null;
    }

    /**
     * BT-31 / BT-48 as written: the identifier with its country prefix when
     * the party is VAT-registered, null otherwise.
     */
    public function vatIdentifier(): ?string
    {
        if (! $this->vatRegistered || $this->taxIdentifier === null) {
            return null;
        }

        return $this->taxIdentifier instanceof Cui
            ? $this->taxIdentifier->withPrefix()
            : $this->prefixed($this->taxIdentifier);
    }

    /**
     * BT-30 / BT-47 as written: the bare identifier when the party is not
     * VAT-registered, the consumer placeholder for a consumer without a CNP.
     */
    public function legalRegistrationIdentifier(): ?string
    {
        if ($this->isConsumer) {
            return $this->taxIdentifier?->value() ?? Cnp::PLACEHOLDER;
        }

        if ($this->vatRegistered || $this->taxIdentifier === null) {
            return null;
        }

        return $this->taxIdentifier->value();
    }

    private function prefixed(TaxIdentifier $identifier): string
    {
        $value = $identifier->value();

        return str_starts_with(strtoupper($value), $identifier->country()) ? $value : $identifier->country().$value;
    }
}
