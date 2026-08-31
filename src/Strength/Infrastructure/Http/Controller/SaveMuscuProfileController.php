<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Application\UseCase\SaveMuscuProfile\SaveMuscuProfileInput;
use Cadence\Strength\Application\UseCase\SaveMuscuProfile\SaveMuscuProfileUseCase;
use Cadence\Strength\Domain\Enum\ExperienceLevel;
use Cadence\Strength\Domain\Enum\GymAccess;
use Cadence\Strength\Domain\Enum\MuscleGroup;
use Cadence\Strength\Domain\Enum\SplitPreference;
use Cadence\Strength\Domain\Enum\StrengthGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SaveMuscuProfileController
{
    public function __construct(
        private readonly SaveMuscuProfileUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $muscles = array_map(static fn (MuscleGroup $m): string => $m->value, MuscleGroup::cases());

        $data = $request->validate([
            'goal' => ['required', Rule::in(array_map(static fn (StrengthGoal $g): string => $g->value, StrengthGoal::cases()))],
            'level' => ['required', Rule::in(array_map(static fn (ExperienceLevel $l): string => $l->value, ExperienceLevel::cases()))],
            'bodyweightKg' => ['nullable', 'numeric', 'between:20,300'],
            'weeklyFrequency' => ['required', 'integer', 'between:1,7'],
            'split' => ['required', Rule::in(array_map(static fn (SplitPreference $s): string => $s->value, SplitPreference::cases()))],
            'equipment' => ['required', Rule::in(array_map(static fn (GymAccess $g): string => $g->value, GymAccess::cases()))],
            'priorities' => ['array'],
            'priorities.*' => [Rule::in($muscles)],
            'limitations' => ['array'],
            'limitations.*' => [Rule::in($muscles)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var list<string> $priorities */
        $priorities = array_values($data['priorities'] ?? []);
        /** @var list<string> $limitations */
        $limitations = array_values($data['limitations'] ?? []);

        $this->useCase->execute(
            new SaveMuscuProfileInput(
                (string) $data['goal'],
                (string) $data['level'],
                isset($data['bodyweightKg']) ? (float) $data['bodyweightKg'] : null,
                (int) $data['weeklyFrequency'],
                (string) $data['split'],
                (string) $data['equipment'],
                $priorities,
                $limitations,
                (string) ($data['note'] ?? ''),
            ),
            new ExecutionContext($this->tenantContext->current()),
        );

        return redirect()->route('muscu.progression')->with('status', 'Profil muscu enregistré 💪');
    }
}
