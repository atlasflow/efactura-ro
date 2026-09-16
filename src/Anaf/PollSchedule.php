<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

/**
 * How long to wait before the next /stareMesaj on a submission, by attempt
 * number. Processing usually takes minutes, occasionally an hour, so the
 * schedule is 30 s, 1, 2, 5, 15, 30 min, then hourly — 6 polls in the first
 * hour and 24 a day after that, well inside the 100-per-message-per-day
 * quota (Quotas::STATUS_PER_MESSAGE_PER_DAY).
 */
final class PollSchedule
{
    /** @var list<int> seconds */
    private const array STEPS = [30, 60, 120, 300, 900, 1800];

    public const int HOURLY = 3600;

    /** The most polls the schedule makes in one day, for the consumer's limiter. */
    public const int MAX_POLLS_PER_DAY = 96;

    /** @param  int  $attempt  1 for the first poll after upload */
    public static function nextDelay(int $attempt): int
    {
        $attempt = max(1, $attempt);

        return self::STEPS[$attempt - 1] ?? self::HOURLY;
    }
}
