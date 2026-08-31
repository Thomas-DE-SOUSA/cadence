<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $goal
 * @property string $level
 * @property float|null $bodyweight_kg
 * @property int $weekly_frequency
 * @property string $split
 * @property string $equipment
 * @property array<int, string> $priorities
 * @property array<int, string> $limitations
 * @property string $note
 */
final class MuscuProfileModel extends Model
{
    protected $table = 'muscu_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['id', 'tenant_id', 'goal', 'level', 'bodyweight_kg', 'weekly_frequency', 'split', 'equipment', 'priorities', 'limitations', 'note'];

    /** @var array<string, string> */
    protected $casts = [
        'bodyweight_kg' => 'float',
        'weekly_frequency' => 'integer',
        'priorities' => 'array',
        'limitations' => 'array',
    ];
}
