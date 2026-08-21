<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $program_id
 * @property string $tenant_id
 */
final class CycleModel extends Model
{
    use SoftDeletes;

    protected $table = 'cycles';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id', 'program_id', 'tenant_id', 'name', 'focus', 'start_date', 'end_date',
        'phase_index', 'status', 'sessions', 'version',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sessions' => 'array',
        'phase_index' => 'integer',
        'version' => 'integer',
    ];
}
