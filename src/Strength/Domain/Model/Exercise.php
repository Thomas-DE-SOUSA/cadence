<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Model;

use Cadence\Strength\Domain\Enum\Equipment;
use Cadence\Strength\Domain\Enum\MuscleGroup;

/**
 * A catalogue exercise. Global library entries have a null tenant (shared by
 * everyone); user-created ones carry the owner's tenant and are flagged custom.
 */
final readonly class Exercise
{
    public function __construct(
        public string $id,
        public ?string $tenantId,
        public string $name,
        public MuscleGroup $primaryMuscle,
        public Equipment $equipment,
        public bool $isCustom = false,
    ) {
    }

    /** @return array<string, mixed> */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'primary_muscle' => $this->primaryMuscle->value,
            'equipment' => $this->equipment->value,
            'is_custom' => $this->isCustom,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromSnapshot(array $data): self
    {
        return new self(
            (string) $data['id'],
            isset($data['tenant_id']) ? (string) $data['tenant_id'] : null,
            (string) $data['name'],
            MuscleGroup::from((string) $data['primary_muscle']),
            Equipment::from((string) $data['equipment']),
            (bool) ($data['is_custom'] ?? false),
        );
    }
}
