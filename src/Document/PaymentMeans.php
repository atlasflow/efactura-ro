<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

/**
 * BG-16 payment instructions. `code` is UNTDID 4461: 30 credit transfer,
 * 31 debit transfer, 42 payment to bank account, 48 bank card, 58 SEPA
 * credit transfer, 10 cash, ZZZ mutually defined. BR-61: 30 and 58 need an
 * account identifier.
 */
final readonly class PaymentMeans
{
    public function __construct(
        public string $code = '31',
        public ?string $iban = null,
        public ?string $accountName = null,
        public ?string $bic = null,
        public ?string $paymentId = null,
        public ?string $text = null,
    ) {}

    public static function bankTransfer(string $iban, ?string $accountName = null, ?string $bic = null): self
    {
        return new self('31', $iban, $accountName, $bic);
    }
}
