<?php

declare(strict_types=1);

use Cadence\Coaching\Domain\Enum\MessageRole;
use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\ValueObject\StrengthWeekSummary;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewContext;
use Cadence\Coaching\Infrastructure\Ai\WeeklyCoachRequestBuilder;
use Cadence\Coaching\Infrastructure\Knowledge\CoachingKnowledge;

function reviewContext(StrengthWeekSummary $strength): WeeklyReviewContext
{
    return new WeeklyReviewContext(
        '2026-08-31',
        '2026-09-06',
        'Sub-40 au 10 km',
        'Odysséa Paris',
        '2026-10-04',
        null,
        'Analyse course : volume en hausse, 80/20 respecté.',
        '01/09: 8.00 km, allure 300 s/km',
        $strength,
    );
}

describe('Feature: weekly coach prompt', function (): void {
    it('assembles the cross-modal system prompt from the athlete data', function (): void {
        $strength = new StrengthWeekSummary(
            '2026-08-31', '2026-09-06',
            sessions: 3, workingSets: 24, tonnageKg: 5200.0, legSessions: 1,
            prevSessions: 2, prevTonnageKg: 4800.0, daysSinceLast: 1,
            muscleBalance: [['muscle' => 'CHEST', 'label' => 'Pectoraux', 'sets' => 9]],
            weightAvgKg: 72.5, weightPrevAvgKg: 73.0,
        );

        $system = (new WeeklyCoachRequestBuilder(new CoachingKnowledge()))->system(reviewContext($strength));

        // Cross-modal doctrine is loaded (weekly note, not just the per-day foundation).
        expect($system)->toContain('Strength × running');
        // Athlete + week framing.
        expect($system)->toContain('du 2026-08-31 au 2026-09-06');
        expect($system)->toContain('Sub-40 au 10 km');
        // Running block reuses the AthleteBrief analysis.
        expect($system)->toContain('80/20 respecté');
        // Strength block surfaces the real numbers.
        expect($system)->toContain('Séances : 3 cette semaine (2 la précédente)');
        expect($system)->toContain('5 200 kg');
        expect($system)->toContain('Pectoraux 9');
        // Mission / priority rules.
        expect($system)->toContain('Ta mission');
        expect($system)->toContain('La course est prioritaire');
    });

    it('states there is no muscu when the week (and the previous) is empty', function (): void {
        $empty = new StrengthWeekSummary('2026-08-31', '2026-09-06', 0, 0, 0.0, 0, 0, 0.0, null, [], null, null);

        $system = (new WeeklyCoachRequestBuilder(new CoachingKnowledge()))->system(reviewContext($empty));

        expect($system)->toContain('Aucune séance de muscu enregistrée');
    });

    it('maps conversation history to alternating roles', function (): void {
        $history = [
            new Message('m1', MessageRole::ATHLETE, 'Ma semaine était bonne ?', '2026-09-07T09:00:00+00:00', null),
            new Message('m2', MessageRole::COACH, 'Oui, solide.', '2026-09-07T09:00:05+00:00', null),
        ];

        $messages = (new WeeklyCoachRequestBuilder(new CoachingKnowledge()))->messages($history);

        expect($messages)->toBe([
            ['role' => 'user', 'content' => 'Ma semaine était bonne ?'],
            ['role' => 'assistant', 'content' => 'Oui, solide.'],
        ]);
    });
});
