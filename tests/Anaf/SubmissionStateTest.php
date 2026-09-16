<?php

declare(strict_types=1);

use AtlasFlow\EFacturaRo\Anaf\PollSchedule;
use AtlasFlow\EFacturaRo\Anaf\Quotas;
use AtlasFlow\EFacturaRo\Anaf\SubmissionState;

it('parses the four stareMesaj values', function () {
    expect(SubmissionState::fromAnaf('ok'))->toBe(SubmissionState::OK)
        ->and(SubmissionState::fromAnaf('nok'))->toBe(SubmissionState::NOK)
        ->and(SubmissionState::fromAnaf('in prelucrare'))->toBe(SubmissionState::PROCESSING)
        ->and(SubmissionState::fromAnaf('XML cu erori nepreluat de sistem'))->toBe(SubmissionState::REFUSED)
        ->and(fn () => SubmissionState::fromAnaf('maybe'))->toThrow(LogicException::class);
});

it('only polls while processing and only moves processing to ok or nok', function () {
    expect(SubmissionState::PROCESSING->canPoll())->toBeTrue()
        ->and(SubmissionState::OK->isTerminal())->toBeTrue()
        ->and(SubmissionState::PROCESSING->next(SubmissionState::OK))->toBe(SubmissionState::OK)
        ->and(SubmissionState::PROCESSING->next(SubmissionState::PROCESSING))->toBe(SubmissionState::PROCESSING)
        ->and(fn () => SubmissionState::OK->next(SubmissionState::NOK))->toThrow(LogicException::class)
        ->and(fn () => SubmissionState::REFUSED->next(SubmissionState::OK))->toThrow(LogicException::class);
});

it('backs off and never exceeds the daily status quota', function () {
    expect(PollSchedule::nextDelay(1))->toBe(30)
        ->and(PollSchedule::nextDelay(2))->toBe(60)
        ->and(PollSchedule::nextDelay(6))->toBe(1800)
        ->and(PollSchedule::nextDelay(7))->toBe(3600)
        ->and(PollSchedule::nextDelay(500))->toBe(3600)
        ->and(PollSchedule::nextDelay(0))->toBe(30)
        ->and(PollSchedule::MAX_POLLS_PER_DAY)->toBeLessThan(Quotas::STATUS_PER_MESSAGE_PER_DAY);

    $elapsed = 0;
    $polls = 0;

    for ($attempt = 1; $elapsed < 86400; $attempt++) {
        $elapsed += PollSchedule::nextDelay($attempt);
        $polls++;
    }

    expect($polls)->toBeLessThanOrEqual(PollSchedule::MAX_POLLS_PER_DAY);
});
