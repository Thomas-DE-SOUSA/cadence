<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Model;

use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\Enum\SessionType;
use Cadence\Training\Domain\ValueObject\CycleId;
use Cadence\Training\Domain\ValueObject\PlannedCycle;
use DateTimeImmutable;

/**
 * @phpstan-import-type PlannedSessionSnapshot from PlannedSession
 *
 * @phpstan-type CycleSnapshot array{id:string,program_id:string,tenant_id:string,name:string,focus:string,start_date:string,end_date:string,sessions:list<PlannedSessionSnapshot>,version:int}
 */
final class Cycle
{
    /**
     * @param list<PlannedSession> $sessions
     */
    private function __construct(
        private readonly CycleId $id,
        private readonly string $programId,
        private readonly TenantId $tenant,
        private readonly string $name,
        private readonly string $focus,
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly array $sessions,
        private readonly int $version,
    ) {
    }

    public static function fromPlan(CycleId $id, string $programId, TenantId $tenant, PlannedCycle $plan, string $startDate): self
    {
        $start = new DateTimeImmutable($startDate);
        $sessions = [];
        $maxOffset = 0;

        foreach ($plan->sessions as $s) {
            $offset = max(0, $s->dayOffset);
            $sessions[] = new PlannedSession(
                $start->modify("+{$offset} days")->format('Y-m-d'),
                SessionType::tryFrom($s->type) ?? SessionType::EASY,
                $s->title,
                $s->description,
                $s->targetDistanceMeters,
                $s->targetDurationSeconds,
                $s->targetPaceSecondsPerKm,
            );
            $maxOffset = max($maxOffset, $offset);
        }

        return new self(
            $id,
            $programId,
            $tenant,
            $plan->name,
            $plan->focus,
            $startDate,
            $start->modify("+{$maxOffset} days")->format('Y-m-d'),
            $sessions,
            version: 1,
        );
    }

    public function id(): CycleId
    {
        return $this->id;
    }

    /** @return CycleSnapshot */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id->value,
            'program_id' => $this->programId,
            'tenant_id' => $this->tenant->value,
            'name' => $this->name,
            'focus' => $this->focus,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'sessions' => array_map(static fn (PlannedSession $s): array => $s->toSnapshot(), $this->sessions),
            'version' => $this->version,
        ];
    }

    /** @param CycleSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            CycleId::fromString($s['id']),
            $s['program_id'],
            TenantId::fromString($s['tenant_id']),
            $s['name'],
            $s['focus'],
            $s['start_date'],
            $s['end_date'],
            array_map(static fn (array $row): PlannedSession => PlannedSession::fromSnapshot($row), $s['sessions']),
            $s['version'],
        );
    }
}
