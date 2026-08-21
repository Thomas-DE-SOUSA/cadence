<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Application\UseCase\ImportActivity\ImportActivityInput;
use Cadence\Activity\Application\UseCase\ImportActivity\ImportActivityUseCase;
use Cadence\Activity\Application\UseCase\RecordActivity\SplitInput;
use Cadence\Activity\Domain\Exception\DuplicateActivity;
use Cadence\Activity\Infrastructure\Gpx\GpxParser;
use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class ImportActivityFromGpxController
{
    public function __construct(
        private readonly ImportActivityUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'gpx' => ['required', 'file', 'max:8192'],
        ]);

        $contents = (string) file_get_contents($request->file('gpx')->getRealPath());
        $summary = GpxParser::summary($contents);
        if ($summary === null || $summary['splits'] === []) {
            return back()->withErrors(['gpx' => 'Ce fichier GPX ne contient pas de tracé exploitable.']);
        }

        try {
            $output = $this->useCase->execute(
                new ImportActivityInput(
                    source: 'GPX',
                    externalId: $summary['externalId'],
                    occurredAt: $summary['occurredAt'],
                    distanceMeters: $summary['distanceMeters'],
                    movingSeconds: $summary['movingSeconds'],
                    elapsedSeconds: $summary['elapsedSeconds'],
                    elevationGainMeters: $summary['elevationGainMeters'],
                    splits: array_map(
                        static fn (array $s): SplitInput => new SplitInput($s['index'], $s['distanceMeters'], $s['durationSeconds'], $s['elevationMeters']),
                        $summary['splits'],
                    ),
                ),
                new ExecutionContext($this->tenantContext->current()),
            );
        } catch (DuplicateActivity) {
            return back()->withErrors(['gpx' => 'Cette sortie semble déjà enregistrée.']);
        } catch (Throwable $e) {
            return back()->withErrors(['gpx' => 'Import impossible : '.$e->getMessage()]);
        }

        if (! $output->imported || $output->activityId === null) {
            return back()->with('status', 'Cette activité est déjà importée.');
        }

        // Attach the route + profile (read-side enrichment, outside the aggregate).
        ActivityModel::query()->whereKey($output->activityId)->update([
            'track' => $summary['track'],
            'stream' => $summary['stream'],
        ]);

        return redirect()->route('activities.show', $output->activityId)->with('status', 'Activité importée depuis le GPX.');
    }
}
