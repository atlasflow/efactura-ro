<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Support\Cnp;

it('accepts a structurally valid CNP with the right check digit', function () {
    expect(Cnp::of('1800101221144')->digits())->toBe('1800101221144')
        ->and(Cnp::of('1800101221144')->country())->toBe('RO');
});

it('rejects a wrong check digit, a bad month and the placeholder', function (string $bad) {
    expect(Cnp::isValid($bad))->toBeFalse();
})->with(['1800101221145', '1801301221144', '0000000000000', '180010122114', 'abcdefghijklm']);

it('exposes the CIUS-RO placeholder for consumers without a CNP', function () {
    expect(Cnp::PLACEHOLDER)->toBe('0000000000000');
});
