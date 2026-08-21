<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Exception;

use Cadence\Shared\Domain\DomainException;

final class CycleGenerationNotAllowed extends DomainException
{
    public static function currentCycleNotCompleted(): self
    {
        return new self(
            ProgramErrorCode::CYCLE_GENERATION_NOT_ALLOWED,
            'Terminez le cycle en cours avant de générer le suivant.',
        );
    }

    public static function roadmapFinished(): self
    {
        return new self(
            ProgramErrorCode::CYCLE_GENERATION_NOT_ALLOWED,
            'La feuille de route est terminée : plus de cycle à générer.',
        );
    }
}
