<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Ubl\UblReader;
use AtlasFlow\EFacturaRo\Ubl\UblWriter;
use AtlasFlow\EFacturaRo\Validation\Rules\BrCo10LineExtensionAmount;
use AtlasFlow\EFacturaRo\Validation\Rules\BrCo11AllowanceTotal;
use AtlasFlow\EFacturaRo\Validation\Rules\BrCo12ChargeTotal;
use AtlasFlow\EFacturaRo\Validation\Rules\BrCo13TaxExclusiveAmount;
use AtlasFlow\EFacturaRo\Validation\Rules\BrCo14TaxTotal;
use AtlasFlow\EFacturaRo\Validation\Rules\BrCo15TaxInclusiveAmount;
use AtlasFlow\EFacturaRo\Validation\Rules\BrCo16PayableAmount;
use AtlasFlow\EFacturaRo\Validation\Rules\BrCo17SubtotalTaxAmount;
use AtlasFlow\EFacturaRo\Validation\Rules\BrCo25PaymentDueDate;
use AtlasFlow\EFacturaRo\Validation\Rules\BreakdownCoversCategories;
use AtlasFlow\EFacturaRo\Validation\Rules\BrIc11IntraCommunityDelivery;
use AtlasFlow\EFacturaRo\Validation\Rules\BrRo001CustomizationId;
use AtlasFlow\EFacturaRo\Validation\Rules\BrRo010NumberHasDigit;
use AtlasFlow\EFacturaRo\Validation\Rules\BrRo100RomanianAddresses;
use AtlasFlow\EFacturaRo\Validation\Rules\BrRoLengthLimits;
use AtlasFlow\EFacturaRo\Validation\Rules\CategoryRates;
use AtlasFlow\EFacturaRo\Validation\Rules\CategoryTaxableAmounts;
use AtlasFlow\EFacturaRo\Validation\Rules\CategoryTaxAmounts;
use AtlasFlow\EFacturaRo\Validation\Rules\DecimalPlaces;
use AtlasFlow\EFacturaRo\Validation\Rules\ExemptionReasons;
use AtlasFlow\EFacturaRo\Validation\Rules\LineNetAmount;
use AtlasFlow\EFacturaRo\Validation\Rules\PartiesIdentified;

/**
 * Everything that can be checked without leaving the machine, in the order
 * ANAF would find it: the XSD first (a structural failure stops here, the
 * rest would be noise), then the arithmetic, identity, EN 16931 and CIUS-RO
 * rules together, then the injected Schematron processor when the model
 * rules found nothing.
 */
final class LocalValidator
{
    /** @var list<Rule> */
    private readonly array $rules;

    /**
     * @param  list<Rule>|null  $rules  defaults to the full rule set; pass your own list to narrow or extend it
     */
    public function __construct(
        private readonly UblWriter $writer = new UblWriter,
        private readonly UblReader $reader = new UblReader,
        private readonly XsdValidator $xsd = new XsdValidator,
        private readonly SchematronValidator $schematron = new NullSchematronValidator,
        ?array $rules = null,
    ) {
        $this->rules = $rules ?? self::defaultRules();
    }

    /** @return list<Rule> */
    public static function defaultRules(): array
    {
        return [
            new DecimalPlaces,
            new LineNetAmount,
            new BrCo10LineExtensionAmount,
            new BrCo11AllowanceTotal,
            new BrCo12ChargeTotal,
            new BrCo13TaxExclusiveAmount,
            new BrCo14TaxTotal,
            new BrCo15TaxInclusiveAmount,
            new BrCo16PayableAmount,
            new BrCo17SubtotalTaxAmount,
            new CategoryTaxableAmounts,
            new CategoryTaxAmounts,
            new CategoryRates,
            new BreakdownCoversCategories,
            new ExemptionReasons,
            new BrCo25PaymentDueDate,
            new BrIc11IntraCommunityDelivery,
            new PartiesIdentified,
            new BrRo001CustomizationId,
            new BrRo010NumberHasDigit,
            new BrRo100RomanianAddresses,
            new BrRoLengthLimits,
        ];
    }

    public function validate(Document $document): ValidationResult
    {
        $xml = $this->writer->write($document);
        $xsd = $this->xsd->validate($xml);

        if (! $xsd->ok) {
            return $xsd;
        }

        $result = $this->rules($document);

        return $result->ok ? $this->schematron->validate($xml) : $result;
    }

    /** Validate XML that came from elsewhere: XSD on the bytes as given, then the rules on what the reader makes of them. */
    public function validateXml(string $xml): ValidationResult
    {
        $xsd = $this->xsd->validate($xml);

        if (! $xsd->ok) {
            return $xsd;
        }

        $result = $this->rules($this->reader->read($xml));

        return $result->ok ? $this->schematron->validate($xml) : $result;
    }

    /** The model rules only — no XSD, no schematron. */
    public function rules(Document $document): ValidationResult
    {
        $errors = [];

        foreach ($this->rules as $rule) {
            $errors = [...$errors, ...$rule->check($document)];
        }

        return ValidationResult::failed($errors);
    }
}
