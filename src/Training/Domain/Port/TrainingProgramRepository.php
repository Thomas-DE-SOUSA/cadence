<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Port;

use Cadence\Shared\Domain\DomainEvent;
use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\Model\TrainingProgram;
use Cadence\Training\Domain\ValueObject\ProgramId;

interface TrainingProgramRepository
{
    /** @param list<DomainEvent> $events */
    public function save(TrainingProgram $program, array $events): void;

    public function ofId(ProgramId $id, TenantId $tenant): ?TrainingProgram;

    /** @return list<TrainingProgram> */
    public function allForTenant(TenantId $tenant): array;
}
