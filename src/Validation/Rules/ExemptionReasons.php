<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation\Rules;

use AtlasFlow\EFacturaRo\Document\Document;
use AtlasFlow\EFacturaRo\Validation\Rule;
use AtlasFlow\EFacturaRo\Validation\ValidationSource;
use AtlasFlow\EFacturaRo\Vat\Vatex;

/**
 * BR-E-10, BR-AE-10, BR-IC-10, BR-G-10, BR-O-10: a breakdown in an
 * exempt-like category needs a VAT exemption reason code (BT-121) or text
 * (BT-120). BR-S-10 and BR-Z-10: Standard and Zero rated breakdowns must
 * not carry either. A reason code, when given, must be on the VATEX list
 * (EN16931-UBL-codes.sch, rule on cbc:TaxExemptionReasonCode).
 */
final class ExemptionReasons implements Rule
{
    use Concerns;

    private const array REQUIRED = ['E' => 'BR-E-10', 'AE' => 'BR-AE-10', 'K' => 'BR-IC-10', 'G' => 'BR-G-10', 'O' => 'BR-O-10'];

    private const array FORBIDDEN = ['S' => 'BR-S-10', 'Z' => 'BR-Z-10'];

    public function check(Document $document): array
    {
        $errors = [];

        foreach ($document->taxSubtotals as $index => $subtotal) {
            $path = sprintf('taxSubtotals[%d].exemptionCode', $index);
            $category = $subtotal->category->value;
            $hasReason = $subtotal->exemptionCode !== null || $subtotal->exemptionReason !== null;

            if (isset(self::REQUIRED[$category]) && ! $hasReason) {
                $errors[] = $this->error(self::REQUIRED[$category], $path, sprintf('A breakdown in category %s needs a VAT exemption reason code or text.', $category), ValidationSource::EN16931);
            }

            if (isset(self::FORBIDDEN[$category]) && $hasReason) {
                $errors[] = $this->error(self::FORBIDDEN[$category], $path, sprintf('A breakdown in category %s must not carry a VAT exemption reason.', $category), ValidationSource::EN16931);
            }

            if ($subtotal->exemptionCode !== null && ! Vatex::isKnown($subtotal->exemptionCode)) {
                $errors[] = $this->error('BR-CL-22', $path, sprintf('"%s" is not a code on the VATEX list.', $subtotal->exemptionCode), ValidationSource::EN16931);
            }
        }

        return $errors;
    }
}
