<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Document\Party;
use AtlasFlow\EFacturaRo\Support\Cui;
use AtlasFlow\EFacturaRo\Support\ForeignIdentifier;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationError;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

/**
 * Fiscal identity of the two parties:
 * - BR-CO-26 / BR-RO-065 / BR-S-02: the seller carries a tax identifier;
 * - BR-RO-120: the buyer carries one, or is a consumer;
 * - ANAF's identity layer (`ERRIdentif`, "CUI … incorect"), which runs after
 *   the schematron: a Romanian party's identifier must be a CUI whose check
 *   digit holds — the reader keeps a bad one as a ForeignIdentifier with
 *   country RO, and that is what this rule catches;
 * - BR-CO-09: a VAT identifier starts with the ISO country code of issue.
 * A foreign buyer (no Romanian identifier) is legal and is flagged for the
 * `extern=DA` upload option by Document::hasForeignBuyer(), not here.
 */
final class PartiesIdentified implements Rule
{
    use Concerns;

    public function check(Document $document): array
    {
        return [
            ...$this->checkParty($document->seller, 'seller', true),
            ...$this->checkParty($document->buyer, 'buyer', false),
        ];
    }

    /** @return list<ValidationError> */
    private function checkParty(Party $party, string $path, bool $isSeller): array
    {
        $id = $party->taxIdentifier;

        if ($party->isConsumer) {
            return [];
        }

        if ($id === null) {
            return [$this->error($isSeller ? 'BR-CO-26' : 'BR-RO-120', "$path.taxIdentifier", sprintf('The %s needs a tax identifier.', $path), ValidationSource::IDENTITY)];
        }

        $errors = [];

        if ($id instanceof ForeignIdentifier && $id->country() === 'RO') {
            $errors[] = $this->error('ERRIdentif', "$path.taxIdentifier", sprintf('"%s" is not a CUI with a valid check digit; ANAF will refuse the %s identifier.', $id->value(), $path), ValidationSource::IDENTITY);
        }

        if ($party->address->isRomanian() && ! $id instanceof Cui && ! ($id instanceof ForeignIdentifier && $id->country() === 'RO')) {
            $errors[] = $this->error('ERRIdentif', "$path.taxIdentifier", sprintf('A %s established in Romania is identified by a CUI, not a %s identifier.', $path, $id->country()), ValidationSource::IDENTITY);
        }

        if ($party->vatRegistered && $id instanceof ForeignIdentifier && ! str_starts_with(strtoupper($id->value()), $id->country())) {
            $errors[] = $this->error('BR-CO-09', "$path.taxIdentifier", sprintf('The %s VAT identifier "%s" must start with its country code %s.', $path, $id->value(), $id->country()), ValidationSource::IDENTITY);
        }

        return $errors;
    }
}
