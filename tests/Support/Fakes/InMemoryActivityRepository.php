<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Activity\Domain\Model\Activity;
use Cadence\Activity\Domain\Port\ActivityRepository;
use Cadence\Activity\Domain\ValueObject\ActivityId;
use Cadence\Shared\Domain\DomainEvent;
use Cadence\Shared\Domain\TenantId;

final class InMemoryActivityRepository implements ActivityRepository
{
    /** @var array<string, Activity> */
    private array $store = [];

    /**
     * Events written "to the outbox" in the same call as the save — lets tests
     * assert atomic persistence without a database.
     *
     * @var list<DomainEvent>
     */
    public array $outbox = [];

    public function save(Activity $activity, array $events): void
    {
        $this->store[$this->key($activity->tenant()->value, $activity->id()->value)] = $activity;

        foreach ($events as $event) {
            $this->outbox[] = $event;
        }
    }

    public function ofId(ActivityId $id, TenantId $tenant): ?Activity
    {
        return $this->store[$this->key($tenant->value, $id->value)] ?? null;
    }

    public function existsForExternalId(TenantId $tenant, ActivitySource $source, string $externalId): bool
    {
        foreach ($this->store as $activity) {
            $snapshot = $activity->toSnapshot();
            if (
                $snapshot['tenant_id'] === $tenant->value
                && $snapshot['source'] === $source->value
                && $snapshot['external_id'] === $externalId
            ) {
                return true;
            }
        }

        return false;
    }

    private function key(string $tenant, string $id): string
    {
        return $tenant.'|'.$id;
    }
}
