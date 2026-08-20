<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\ImportActivityFromText;

use Cadence\Activity\Application\Port\StravaTextParser;
use Cadence\Activity\Application\UseCase\ImportActivity\ImportActivityInput;
use Cadence\Activity\Application\UseCase\ImportActivity\ImportActivityOutput;
use Cadence\Activity\Application\UseCase\ImportActivity\ImportActivityUseCase;
use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Shared\Application\ExecutionContext;

/**
 * Records an activity from pasted text: parse it, then import it (dedup included).
 * Pure orchestration — the parsing lives behind a port, the recording in the aggregate.
 */
final readonly class ImportActivityFromTextUseCase
{
    public function __construct(
        private StravaTextParser $parser,
        private ImportActivityUseCase $import,
    ) {
    }

    public function execute(string $rawText, ExecutionContext $context): ImportActivityOutput
    {
        $parsed = $this->parser->parse($rawText);

        $input = new ImportActivityInput(
            source: ActivitySource::STRAVA->value,
            // Fall back to a content hash so pasting the same text twice is idempotent.
            externalId: $parsed->externalId !== '' ? $parsed->externalId : 'paste-'.sha1(trim($rawText)),
            occurredAt: $parsed->occurredAtIso,
            distanceMeters: $parsed->distanceMeters,
            movingSeconds: $parsed->movingSeconds,
            elapsedSeconds: $parsed->elapsedSeconds,
            elevationGainMeters: $parsed->elevationGainMeters,
            splits: $parsed->splits,
            bestEfforts: $parsed->bestEfforts,
        );

        return $this->import->execute($input, $context);
    }
}
