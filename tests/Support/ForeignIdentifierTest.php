<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Support\ForeignIdentifier;

it('carries a value and an upper-cased country', function () {
    $id = ForeignIdentifier::of('DE123456789', 'de');

    expect($id->value())->toBe('DE123456789')
        ->and($id->country())->toBe('DE');
});

it('refuses an empty value or a bad country code', function () {
    expect(fn () => ForeignIdentifier::of('', 'DE'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ForeignIdentifier::of('X', 'Germany'))->toThrow(InvalidArgumentException::class);
});
