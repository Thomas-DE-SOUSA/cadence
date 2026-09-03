<?php

declare(strict_types=1);

use Cadence\Coaching\Domain\Enum\MessageRole;
use Cadence\Coaching\Domain\Model\WeeklyReview;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewId;
use Cadence\Shared\Domain\TenantId;

function newReview(): WeeklyReview
{
    return WeeklyReview::start(WeeklyReviewId::fromString('wr-1'), TenantId::fromString('tenant-thomas'), '2026-08-31');
}

describe('Feature: weekly review aggregate', function (): void {
    it('starts empty at version 1 for the given week', function (): void {
        $review = newReview();

        expect($review->weekStart())->toBe('2026-08-31');
        expect($review->messages())->toBe([]);
        expect($review->toSnapshot()['version'])->toBe(1);
    });

    it('records athlete and coach turns in order and bumps the version', function (): void {
        $review = newReview();
        $review->addAthleteMessage('m1', 'Ma semaine ?', '2026-09-07T09:00:00+00:00');
        $review->addCoachMessage('m2', 'Solide, muscu bien placée.', '2026-09-07T09:00:05+00:00');

        $messages = $review->messages();
        expect($messages)->toHaveCount(2);
        expect($messages[0]->role)->toBe(MessageRole::ATHLETE);
        expect($messages[1]->role)->toBe(MessageRole::COACH);
        expect($messages[1]->proposal)->toBeNull();
        expect($review->toSnapshot()['version'])->toBe(3); // 1 + 2 turns
    });

    it('round-trips through its snapshot without loss', function (): void {
        $review = newReview();
        $review->addAthleteMessage('m1', 'Question', '2026-09-07T09:00:00+00:00');
        $review->addCoachMessage('m2', 'Réponse', '2026-09-07T09:00:05+00:00');

        $restored = WeeklyReview::fromSnapshot($review->toSnapshot());

        expect($restored->toSnapshot())->toBe($review->toSnapshot());
        expect($restored->id()->value)->toBe('wr-1');
        expect($restored->weekStart())->toBe('2026-08-31');
        expect($restored->messages()[0]->text)->toBe('Question');
    });

    it('rejects an empty id', function (): void {
        expect(fn (): WeeklyReviewId => WeeklyReviewId::fromString('  '))
            ->toThrow(InvalidArgumentException::class);
    });
});
