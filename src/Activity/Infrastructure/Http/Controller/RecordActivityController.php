<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Application\UseCase\RecordActivity\RecordActivityUseCase;
use Cadence\Activity\Infrastructure\Http\Request\RecordActivityRequest;
use Illuminate\Http\JsonResponse;

final class RecordActivityController
{
    public function __construct(private readonly RecordActivityUseCase $useCase)
    {
    }

    public function __invoke(RecordActivityRequest $request): JsonResponse
    {
        $output = $this->useCase->execute($request->toInput());

        return new JsonResponse([
            'activity_id' => $output->activityId,
            'average_pace_seconds_per_km' => $output->averagePaceSecondsPerKm,
        ], JsonResponse::HTTP_CREATED);
    }
}
