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

    /**
     * A credit note or a corrective invoice refers to something, so a caller
     * building one should supply BT-25. ANAF's validator does not insist on
     * it (checked 2026-09-16 with MF's own credit note sample), so neither
     * does construction; this is advice for eligibility checks upstream.
     */
    public function expectsPrecedingDocuments(): bool
    {
        return $this === self::CREDIT_NOTE || $this === self::CORRECTED_INVOICE;
    }

    public function ublRootElement(): string
    {
        return $this->isCreditNote() ? 'CreditNote' : 'Invoice';
    }
}
