<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string $primary_muscle
 * @property string $equipment
 * @property bool $is_custom
 */
final class ExerciseModel extends Model
{
    protected $table = 'exercises';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['id', 'tenant_id', 'name', 'primary_muscle', 'equipment', 'is_custom'];

    /** @var array<string, string> */
    protected $casts = ['is_custom' => 'boolean'];
}
