<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\ImportActivityFromPhoto;

use Cadence\Activity\Application\Port\ActivityPhotoParser;
use Cadence\Activity\Application\UseCase\ImportActivity\ImportActivityInput;
use Cadence\Activity\Application\UseCase\ImportActivity\ImportActivityOutput;
use Cadence\Activity\Application\UseCase\ImportActivity\ImportActivityUseCase;
use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Shared\Application\ExecutionContext;

/**
 * Records an activity from a photo of a watch/app screen: read it with vision,
 * then import it (dedup included). Pure orchestration.
 */
final readonly class ImportActivityFromPhotoUseCase
{
    public function __construct(
        private ActivityPhotoParser $parser,
        private ImportActivityUseCase $import,
    ) {
    }

    public function execute(
        string $imageBytes,
        string $mimeType,
        ExecutionContext $context,
        ?string $occurredAtOverride = null,
    ): ImportActivityOutput {
        $parsed = $this->parser->parse($imageBytes, $mimeType);

        $occurredAt = $occurredAtOverride !== null && $occurredAtOverride !== ''
            ? $occurredAtOverride
            : $parsed->occurredAtIso;

        $input = new ImportActivityInput(
            source: ActivitySource::MANUAL->value,
            // Same photo twice is idempotent.
            externalId: 'photo-'.sha1($imageBytes),
            occurredAt: $occurredAt,
            distanceMeters: $parsed->distanceMeters,
            movingSeconds: $parsed->movingSeconds,
            elapsedSeconds: $parsed->elapsedSeconds,
            elevationGainMeters: $parsed->elevationGainMeters,
            splits: [],
            bestEfforts: [],
        );

        return $this->import->execute($input, $context);
    }
}
