<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 */
final class TrainingProgramModel extends Model
{
    use SoftDeletes;

    protected $table = 'training_programs';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id', 'tenant_id', 'name', 'goal', 'target_race_name', 'target_race_date',
        'start_date', 'end_date', 'priority', 'status', 'objectives', 'assigned_activity_ids', 'version',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'objectives' => 'array',
        'assigned_activity_ids' => 'array',
        'version' => 'integer',
    ];
}
