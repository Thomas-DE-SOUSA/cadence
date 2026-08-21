<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Model;

use Cadence\Shared\Domain\AggregateRoot;
use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\Enum\ProgramPriority;
use Cadence\Training\Domain\Enum\ProgramStatus;
use Cadence\Training\Domain\Event\ActivityAssignedToProgram;
use Cadence\Training\Domain\Event\ActivityUnassignedFromProgram;
use Cadence\Training\Domain\Event\ProgramCreated;
use Cadence\Training\Domain\Exception\InvalidProgram;
use Cadence\Training\Domain\Exception\ProgramErrorCode;
use Cadence\Training\Domain\ValueObject\ProgramId;
use DateTimeImmutable;

/**
 * @phpstan-import-type ObjectiveSnapshot from Objective
 *
 * @phpstan-type ProgramSnapshot array{id:string,tenant_id:string,name:string,goal:string,target_race_name:string,target_race_date:string|null,start_date:string,end_date:string|null,priority:string,status:string,plan_key:string|null,objectives:list<ObjectiveSnapshot>,assigned_activity_ids:list<string>,version:int}
 */
final class TrainingProgram extends AggregateRoot
{
    /**
     * @param list<Objective> $objectives
     * @param list<string> $assignedActivityIds
     */
    private function __construct(
        private readonly ProgramId $id,
        private readonly TenantId $tenant,
        private readonly string $name,
        private readonly string $goal,
        private readonly string $targetRaceName,
        private readonly ?string $targetRaceDate,
        private readonly string $startDate,
        private readonly ?string $endDate,
        private readonly ProgramPriority $priority,
        private ProgramStatus $status,
        private readonly ?string $planKey,
        private readonly array $objectives,
        private array $assignedActivityIds,
        private int $version,
    ) {
    }

    /**
     * @param list<Objective> $objectives
     */
    public static function create(
        ProgramId $id,
        TenantId $tenant,
        string $name,
        string $goal,
        string $targetRaceName,
        ?string $targetRaceDate,
        string $startDate,
        ?string $endDate,
        ProgramPriority $priority,
        array $objectives,
        DateTimeImmutable $recordedAt,
        ?string $planKey = null,
    ): self {
        if (trim($name) === '') {
            throw new InvalidProgram(ProgramErrorCode::NAME_REQUIRED, 'A program needs a name.');
        }

        $program = new self(
            $id, $tenant, $name, $goal, $targetRaceName, $targetRaceDate, $startDate, $endDate,
            $priority, ProgramStatus::PLANNED, $planKey, $objectives, [], version: 1,
        );

        $program->recordEvent(new ProgramCreated($id->value, $recordedAt, $tenant->value, $name));

        return $program;
    }

    public function assignActivity(string $activityId, DateTimeImmutable $recordedAt): void
    {
        if (in_array($activityId, $this->assignedActivityIds, true)) {
            return;
        }

        $this->assignedActivityIds[] = $activityId;
        $this->version++;
        $this->recordEvent(new ActivityAssignedToProgram($this->id->value, $recordedAt, $this->tenant->value, $activityId));
    }

    public function unassignActivity(string $activityId, DateTimeImmutable $recordedAt): void
    {
        if (! in_array($activityId, $this->assignedActivityIds, true)) {
            return;
        }

        $this->assignedActivityIds = array_values(
            array_filter($this->assignedActivityIds, static fn (string $a): bool => $a !== $activityId),
        );
        $this->version++;
        $this->recordEvent(new ActivityUnassignedFromProgram($this->id->value, $recordedAt, $this->tenant->value, $activityId));
    }

    public function complete(): void
    {
        $this->status = ProgramStatus::COMPLETED;
        $this->version++;
    }

    public function id(): ProgramId
    {
        return $this->id;
    }

    public function planKey(): ?string
    {
        return $this->planKey;
    }

    public function startDate(): string
    {
        return $this->startDate;
    }

    public function tenant(): TenantId
    {
        return $this->tenant;
    }

    public function version(): int
    {
        return $this->version;
    }

    /** @return list<Objective> */
    public function objectives(): array
    {
        return $this->objectives;
    }

    /** @return list<string> */
    public function assignedActivityIds(): array
    {
        return $this->assignedActivityIds;
    }

    /** @return ProgramSnapshot */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id->value,
            'tenant_id' => $this->tenant->value,
            'name' => $this->name,
            'goal' => $this->goal,
            'target_race_name' => $this->targetRaceName,
            'target_race_date' => $this->targetRaceDate,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'plan_key' => $this->planKey,
            'objectives' => array_map(static fn (Objective $o): array => $o->toSnapshot(), $this->objectives),
            'assigned_activity_ids' => $this->assignedActivityIds,
            'version' => $this->version,
        ];
    }

    /** @param ProgramSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            ProgramId::fromString($s['id']),
            TenantId::fromString($s['tenant_id']),
            $s['name'],
            $s['goal'],
            $s['target_race_name'],
            $s['target_race_date'],
            $s['start_date'],
            $s['end_date'],
            ProgramPriority::from($s['priority']),
            ProgramStatus::from($s['status']),
            $s['plan_key'],
            array_map(static fn (array $row): Objective => Objective::fromSnapshot($row), $s['objectives']),
            $s['assigned_activity_ids'],
            $s['version'],
        );
    }
}
