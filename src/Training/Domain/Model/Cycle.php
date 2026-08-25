<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Model;

use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\Enum\CycleStatus;
use Cadence\Training\Domain\Enum\SessionType;
use Cadence\Training\Domain\ValueObject\CycleId;
use Cadence\Training\Domain\ValueObject\PlannedCycle;
use DateTimeImmutable;

/**
 * @phpstan-import-type PlannedSessionSnapshot from PlannedSession
 *
 * @phpstan-type CycleSnapshot array{id:string,program_id:string,tenant_id:string,name:string,focus:string,start_date:string,end_date:string,phase_index:int,status:string,sessions:list<PlannedSessionSnapshot>,version:int}
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
        private readonly int $phaseIndex,
        private CycleStatus $status,
        private array $sessions,
        private int $version,
    ) {
    }

    public static function fromPlan(
        CycleId $id,
        string $programId,
        TenantId $tenant,
        PlannedCycle $plan,
        string $startDate,
        int $phaseIndex = 0,
        bool $suggestDays = true,
    ): self {
        $start = new DateTimeImmutable($startDate);
        $sessions = [];
        $maxOffset = 0;

        foreach ($plan->sessions as $s) {
            $offset = max(0, $s->dayOffset);
            $date = $start->modify("+{$offset} days")->format('Y-m-d');
            $sessions[] = new PlannedSession(
                $date,
                SessionType::tryFrom($s->type) ?? SessionType::EASY,
                $s->title,
                $s->description,
                $s->targetDistanceMeters,
                $s->targetDurationSeconds,
                $s->targetPaceSecondsPerKm,
                $s->steps,
                null,
                // Fixed-schedule athletes keep a visible day hint; flexible ones
                // (no preferred days) get no suggested day — just "this week".
                $suggestDays ? $date : null,
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
            $phaseIndex,
            CycleStatus::ACTIVE,
            $sessions,
            version: 1,
        );
    }

    public function complete(): void
    {
        $this->status = CycleStatus::COMPLETED;
        $this->version++;
    }

    /** Link an activity to the planned session on the given date. */
    public function linkActivity(string $date, ?string $activityId): void
    {
        $changed = false;
        $this->sessions = array_map(static function (PlannedSession $s) use ($date, $activityId, &$changed): PlannedSession {
            if ($s->date === $date) {
                $changed = true;

                return $s->withActivity($activityId);
            }

            return $s;
        }, $this->sessions);

        if ($changed) {
            $this->version++;
        }
    }

    /** Replace the planned session on a date, keeping any linked activity. */
    public function replaceSession(
        string $date,
        SessionType $type,
        string $title,
        string $description,
        ?int $targetDistanceMeters,
        ?int $targetDurationSeconds,
        ?int $targetPaceSecondsPerKm,
    ): void {
        $changed = false;
        $this->sessions = array_map(static function (PlannedSession $s) use ($date, $type, $title, $description, $targetDistanceMeters, $targetDurationSeconds, $targetPaceSecondsPerKm, &$changed): PlannedSession {
            if ($s->date === $date) {
                $changed = true;

                return new PlannedSession($date, $type, $title, $description, $targetDistanceMeters, $targetDurationSeconds, $targetPaceSecondsPerKm, [], $s->activityId, $s->suggestedDate);
            }

            return $s;
        }, $this->sessions);

        if ($changed) {
            $this->version++;
        }
    }

    public function programId(): string
    {
        return $this->programId;
    }

    public function id(): CycleId
    {
        return $this->id;
    }

    public function phaseIndex(): int
    {
        return $this->phaseIndex;
    }

    public function status(): CycleStatus
    {
        return $this->status;
    }

    public function isCompleted(): bool
    {
        return $this->status === CycleStatus::COMPLETED;
    }

    public function startDate(): string
    {
        return $this->startDate;
    }

    public function endDate(): string
    {
        return $this->endDate;
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
            'phase_index' => $this->phaseIndex,
            'status' => $this->status->value,
            'sessions' => array_map(static fn (PlannedSession $s): array => $s->toSnapshot(), $this->sessions),
            'version' => $this->version,
        ];
    }

    /** @param CycleSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        $sessions = array_map(static fn (array $row): PlannedSession => PlannedSession::fromSnapshot($row), $s['sessions']);

        return new self(
            CycleId::fromString($s['id']),
            $s['program_id'],
            TenantId::fromString($s['tenant_id']),
            $s['name'],
            $s['focus'],
            $s['start_date'],
            $s['end_date'],
            $s['phase_index'],
            CycleStatus::from($s['status']),
            $sessions,
            $s['version'],
        );
    }
}
