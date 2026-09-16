<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Support\Cui;

it('normalises the RO prefix and whitespace', function () {
    expect(Cui::of('RO12345674')->digits())->toBe('12345674')
        ->and(Cui::of('ro 12345674')->digits())->toBe('12345674')
        ->and(Cui::of(' 12345674 ')->withPrefix())->toBe('RO12345674')
        ->and((string) Cui::of('12345674'))->toBe('12345674')
        ->and(Cui::of('12345674')->country())->toBe('RO');
});

it('accepts identifiers whose check digit holds', function (string $cui) {
    expect(Cui::isValid($cui))->toBeTrue();
})->with(['14399840', '18189442', '8000000000', '40000000', '19', '124']);

it('rejects identifiers whose check digit fails', function (string $cui) {
    expect(Cui::isValid($cui))->toBeFalse();
})->with(['12345678', '1234567890', '987456123', '123456', '13']);

it('rejects malformed input', function (string $bad) {
    Cui::of($bad);
})->with(['', 'RO', '1', '12345678901', 'ROabc', 'DE123456'])
    ->throws(InvalidArgumentException::class);

it('compares by digits', function () {
    expect(Cui::of('RO12345674')->equals(Cui::of('12345674')))->toBeTrue()
        ->and(Cui::of('12345674')->equals(Cui::of('40000000')))->toBeFalse();
});
