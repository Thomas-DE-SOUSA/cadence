<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property int $version
 * @property array<int, array<string, mixed>> $exercises
 */
final class WorkoutTemplateModel extends Model
{
    use SoftDeletes;

    protected $table = 'workout_templates';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['id', 'tenant_id', 'name', 'version', 'exercises'];

    /** @var array<string, string> */
    protected $casts = [
        'exercises' => 'array',
        'version' => 'integer',
    ];
}
