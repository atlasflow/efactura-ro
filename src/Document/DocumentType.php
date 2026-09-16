<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Document;

/**
 * UNTDID 1001 type codes ANAF accepts (BR-RO-020). Everything but 381 is
 * written as a UBL Invoice; 381 is a UBL CreditNote.
 */
enum DocumentType: string
{
    case INVOICE = '380';

    case CREDIT_NOTE = '381';

    case CORRECTED_INVOICE = '384';

    case SELF_BILLED = '389';

    case ACCOUNTING_INFORMATION = '751';

    public function isCreditNote(): bool
    {
        return $this === self::CREDIT_NOTE;
    }

    /** BT-25 is mandatory for a credit note or a corrective invoice: something is being credited or corrected. */
    public function requiresPrecedingDocuments(): bool
    {
        return $this === self::CREDIT_NOTE || $this === self::CORRECTED_INVOICE;
    }

    public function ublRootElement(): string
    {
        return $this->isCreditNote() ? 'CreditNote' : 'Invoice';
    }
}
