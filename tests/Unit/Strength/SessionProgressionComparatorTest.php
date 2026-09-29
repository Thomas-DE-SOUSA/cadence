<?php

declare(strict_types=1);

use Cadence\Strength\Domain\Enum\ProgressionKind;
use Cadence\Strength\Domain\Model\StrengthSession;
use Cadence\Strength\Domain\Service\SessionProgressionComparator;
use Cadence\Strength\Domain\ValueObject\PerformedExercise;
use Cadence\Strength\Domain\ValueObject\SetEntry;

/**
 * @param list<SetEntry> $sets
 */
function perf(string $id, string $name, array $sets): PerformedExercise
{
    return new PerformedExercise($id, $name, $sets);
}

describe('SessionProgressionComparator', function (): void {
    $cmp = new SessionProgressionComparator();

    it('flags a heavier top working set as a WEIGHT progression', function () use ($cmp): void {
        $prev = perf('bench', 'Développé couché', [new SetEntry(60, 10), new SetEntry(60, 10)]);
        $cur = perf('bench', 'Développé couché', [new SetEntry(62.5, 10), new SetEntry(62.5, 10)]);

        $p = $cmp->compare($cur, $prev);

        expect($p)->not->toBeNull();
        expect($p->kind)->toBe(ProgressionKind::WEIGHT);
        expect($p->fromWeightKg)->toBe(60.0);
        expect($p->toWeightKg)->toBe(62.5);
        expect($p->weightDeltaKg())->toBe(2.5);
    });

    it('flags more reps at the same top weight as a REPS progression', function () use ($cmp): void {
        $prev = perf('bench', 'Développé couché', [new SetEntry(60, 8)]);
        $cur = perf('bench', 'Développé couché', [new SetEntry(60, 10)]);

        $p = $cmp->compare($cur, $prev);

        expect($p->kind)->toBe(ProgressionKind::REPS);
        expect($p->fromReps)->toBe(8);
        expect($p->toReps)->toBe(10);
        expect($p->repsDelta())->toBe(2);
    });

    it('returns null when neither weight nor reps improved', function () use ($cmp): void {
        $prev = perf('bench', 'Développé couché', [new SetEntry(60, 10)]);
        $cur = perf('bench', 'Développé couché', [new SetEntry(60, 10)]);

        expect($cmp->compare($cur, $prev))->toBeNull();
    });

    it('returns null when the top weight dropped, even with more reps', function () use ($cmp): void {
        $prev = perf('bench', 'Développé couché', [new SetEntry(62.5, 10)]);
        $cur = perf('bench', 'Développé couché', [new SetEntry(60, 12)]);

        expect($cmp->compare($cur, $prev))->toBeNull();
    });

    it('has no reference the first time an exercise is performed', function () use ($cmp): void {
        $cur = perf('bench', 'Développé couché', [new SetEntry(60, 10)]);

        expect($cmp->compare($cur, null))->toBeNull();
    });

    it('ignores warm-up sets when picking the top set', function () use ($cmp): void {
        $prev = perf('bench', 'Développé couché', [new SetEntry(60, 10)]);
        // A heavy warm-up single must not be read as a working-set progression.
        $cur = perf('bench', 'Développé couché', [
            new SetEntry(100, 1, null, null, isWarmup: true),
            new SetEntry(60, 10),
        ]);

        expect($cmp->compare($cur, $prev))->toBeNull();
    });

    it('flags more reps on a bodyweight exercise as a REPS progression', function () use ($cmp): void {
        $prev = perf('pullup', 'Tractions', [new SetEntry(null, 8)]);
        $cur = perf('pullup', 'Tractions', [new SetEntry(null, 10)]);

        $p = $cmp->compare($cur, $prev);

        expect($p->kind)->toBe(ProgressionKind::REPS);
        expect($p->repsDelta())->toBe(2);
    });

    it('compares a whole session against previous performances per exercise', function () use ($cmp): void {
        $current = new StrengthSession('s3', 'tenant-thomas', '2026-09-29', 'Push', '', 3120, [
            perf('bench', 'Développé couché', [new SetEntry(62.5, 10)]),   // +2.5 kg
            perf('ohp', 'Développé militaire', [new SetEntry(40, 8)]),      // stable
            perf('dips', 'Dips', [new SetEntry(null, 12)]),                 // first time
        ]);
        $previous = [
            'bench' => perf('bench', 'Développé couché', [new SetEntry(60, 10)]),
            'ohp' => perf('ohp', 'Développé militaire', [new SetEntry(40, 8)]),
        ];

        $progs = $cmp->compareSession($current, $previous);

        expect($progs)->toHaveCount(1);
        expect($progs[0]->exerciseId)->toBe('bench');
        expect($progs[0]->kind)->toBe(ProgressionKind::WEIGHT);
    });
});
