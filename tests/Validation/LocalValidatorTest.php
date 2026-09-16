<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Tests\Fixtures\Documents;
use AtlasFlow\EFacturaRo\Validation\LocalValidator;
use AtlasFlow\EFacturaRo\Validation\SchematronValidator;
use AtlasFlow\EFacturaRo\Validation\ValidationError;
use AtlasFlow\EFacturaRo\Validation\ValidationResult;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;

it('passes every fixture document', function (string $name) {
    $result = (new LocalValidator)->validate(Documents::all()[$name]());

    expect($result->ok)->toBeTrue(implode("\n", array_map('strval', $result->errors)));
})->with(array_keys(Documents::all()));

it('flags what ANAF would flag on the Ministry of Finance sample', function () {
    $result = (new LocalValidator)->validateXml(fixtureFile('mf/eInvoice_ex.xml'));

    expect($result->ok)->toBeFalse()
        ->and($result->has('BR-RO-001'))->toBeTrue()
        ->and($result->has('ERRIdentif'))->toBeTrue()
        ->and($result->from(ValidationSource::IDENTITY))->toHaveCount(2)
        ->and($result->has('BR-CO-10'))->toBeFalse();
});

it('stops at the XSD layer when the XML is structurally broken', function () {
    $result = (new LocalValidator)->validateXml('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"><Bogus/></Invoice>');

    expect($result->ok)->toBeFalse()
        ->and($result->errors[0]->source)->toBe(ValidationSource::XSD)
        ->and($result->from(ValidationSource::ARITHMETIC))->toBe([]);
});

it('refuses XML whose root is not an invoice at the XSD layer', function () {
    $result = (new LocalValidator)->validateXml('<Order xmlns="urn:x"/>');

    expect($result->ok)->toBeFalse()
        ->and($result->has('XSD-ROOT'))->toBeTrue();
});

it('runs the injected schematron only when the model rules pass', function () {
    $schematron = new class implements SchematronValidator
    {
        public int $calls = 0;

        public function validate(string $xml): ValidationResult
        {
            $this->calls++;

            return ValidationResult::failed([new ValidationError('BR-RO-X', '/Invoice', 'from schematron', ValidationSource::SCHEMATRON)]);
        }
    };

    $validator = new LocalValidator(schematron: $schematron);

    $good = $validator->validate(Documents::standardInvoice());

    expect($good->ok)->toBeFalse()
        ->and($good->errors[0]->source)->toBe(ValidationSource::SCHEMATRON)
        ->and($schematron->calls)->toBe(1);
});

it('exposes codes and per-source filtering on a result', function () {
    $result = ValidationResult::failed([
        new ValidationError('A', 'x', 'm', ValidationSource::ARITHMETIC),
        new ValidationError('A', 'y', 'm', ValidationSource::ARITHMETIC),
        new ValidationError('B', 'z', 'm', ValidationSource::CIUS_RO),
    ]);

    expect($result->codes())->toBe(['A', 'B'])
        ->and($result->from(ValidationSource::CIUS_RO))->toHaveCount(1)
        ->and($result->merge(ValidationResult::ok())->errors)->toHaveCount(3)
        ->and(ValidationResult::failed([])->ok)->toBeTrue()
        ->and((string) $result->errors[2])->toBe('[cius-ro] B at z: m');
});
