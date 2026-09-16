<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

use LogicException;

/**
 * The state machine of one upload, as /stareMesaj reports it:
 *   PROCESSING ("in prelucrare") → OK | NOK
 *   REFUSED ("XML cu erori nepreluat de sistem") is terminal from the start:
 *   the upload response already carried the reason.
 */
enum SubmissionState: string
{
    case PROCESSING = 'processing';

    /** Validated and delivered to the buyer's SPV; the signed bundle can be downloaded. */
    case OK = 'ok';

    /** Validation failed; the errors bundle can be downloaded; the buyer never sees it. */
    case NOK = 'nok';

    /** Refused at upload time. */
    case REFUSED = 'refused';

    public static function fromAnaf(string $stare): self
    {
        return match (trim($stare)) {
            'ok' => self::OK,
            'nok' => self::NOK,
            'in prelucrare' => self::PROCESSING,
            'XML cu erori nepreluat de sistem' => self::REFUSED,
            default => throw new LogicException(sprintf('Unknown stareMesaj value "%s".', $stare)),
        };
    }

    public function canPoll(): bool
    {
        return $this === self::PROCESSING;
    }

    public function isTerminal(): bool
    {
        return $this !== self::PROCESSING;
    }

    /** The transitions ANAF can make; anything else is a bug in the caller's bookkeeping. */
    public function next(SubmissionState $to): self
    {
        if ($this === $to) {
            return $to;
        }

        if ($this === self::PROCESSING && ($to === self::OK || $to === self::NOK)) {
            return $to;
        }

        throw new LogicException(sprintf('A submission cannot go from %s to %s.', $this->value, $to->value));
    }
}
