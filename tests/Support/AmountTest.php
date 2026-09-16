<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Support\Amount;

it('is built from a decimal string and keeps its scale', function () {
    expect(Amount::of('12.30')->toString())->toBe('12.30')
        ->and(Amount::of('-0.5')->toString())->toBe('-0.5')
        ->and(Amount::of(7)->toString())->toBe('7')
        ->and(Amount::of(' 100.00 ')->scale())->toBe(2);
});

it('refuses floats at construction', function () {
    Amount::of(12.3);
})->throws(InvalidArgumentException::class, 'never floats');

it('refuses strings that are not plain decimals', function (string $bad) {
    Amount::of($bad);
})->with(['', '1,5', '1e3', 'abc', '12.', '.5', '1 000'])
    ->throws(InvalidArgumentException::class);

it('does exact arithmetic', function () {
    $a = Amount::of('0.10');
    $b = Amount::of('0.20');

    expect($a->plus($b)->toString())->toBe('0.30')
        ->and($a->plus($b)->equals('0.3'))->toBeTrue()
        ->and(Amount::of('100.00')->multipliedBy('0.19')->toString())->toBe('19.0000')
        ->and(Amount::of('100.00')->multipliedBy('0.19')->rounded()->toString())->toBe('19.00')
        ->and(Amount::of('2.675')->rounded()->toString())->toBe('2.68')
        ->and(Amount::of('-2.675')->rounded()->toString())->toBe('-2.68')
        ->and(Amount::of('10')->dividedBy('3', 2)->toString())->toBe('3.33');
});

it('sums a list and an empty list is zero', function () {
    expect(Amount::sum([Amount::of('1.10'), Amount::of('2.20'), Amount::of('-0.30')])->toString())->toBe('3.00')
        ->and(Amount::sum([])->toString())->toBe('0.00');
});

it('compares numerically', function () {
    expect(Amount::of('1.5')->isGreaterThan('1.49'))->toBeTrue()
        ->and(Amount::of('0')->isZero())->toBeTrue()
        ->and(Amount::of('0.00')->isZero())->toBeTrue()
        ->and(Amount::of('-1')->isNegative())->toBeTrue()
        ->and(Amount::of('-1')->abs()->toString())->toBe('1')
        ->and(Amount::of('1')->negated()->toString())->toBe('-1');
});
