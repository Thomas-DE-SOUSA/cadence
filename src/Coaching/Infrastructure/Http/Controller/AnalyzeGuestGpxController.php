<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Gpx\GpxParser;
use Cadence\Coaching\Infrastructure\Read\GuestAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Parses one or more guest GPX files into measured efforts, VDOT and projections. */
final class AnalyzeGuestGpxController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'gpx' => ['required', 'array', 'max:10'],
            'gpx.*' => ['file', 'max:8192'],
        ]);

        /** @var list<\Illuminate\Http\UploadedFile> $files */
        $files = array_values(array_filter((array) $request->file('gpx')));

        $runs = [];
        /** @var array<int, int> $bestByDistance */
        $bestByDistance = [];
        foreach ($files as $file) {
            $path = $file->getRealPath();
            if (! is_string($path)) {
                continue;
            }
            $summary = GpxParser::summary((string) file_get_contents($path));
            if ($summary === null) {
                continue;
            }

            $splits = array_map(
                static fn (array $s): array => ['distanceMeters' => (int) $s['distanceMeters'], 'durationSeconds' => (int) $s['durationSeconds']],
                $summary['splits'],
            );
            foreach (GuestAssessment::bestEffortsFromSplits($splits) as $distance => $seconds) {
                if (! isset($bestByDistance[$distance]) || $seconds < $bestByDistance[$distance]) {
                    $bestByDistance[$distance] = $seconds;
                }
            }

            $runs[] = [
                'label' => $file->getClientOriginalName(),
                'distanceMeters' => $summary['distanceMeters'],
                'movingSeconds' => $summary['movingSeconds'],
            ];
        }

        return response()->json([
            'runs' => $runs,
            ...GuestAssessment::assess($bestByDistance),
        ]);
    }
}
