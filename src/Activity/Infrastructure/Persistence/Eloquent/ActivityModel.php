<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent record for the Activity aggregate. Infrastructure only — the domain
 * never imports this class. Child collections are stored as JSON because the
 * aggregate is always loaded and saved as a whole.
 *
 * @property string $id
 * @property string $tenant_id
 */
final class ActivityModel extends Model
{
    use SoftDeletes;

    protected $table = 'activities';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'tenant_id',
        'occurred_at',
        'source',
        'distance_meters',
        'moving_seconds',
        'elapsed_seconds',
        'elevation_gain_meters',
        'average_pace_seconds_per_km',
        'splits',
        'best_efforts',
        'external_id',
        'version',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'distance_meters' => 'integer',
        'moving_seconds' => 'integer',
        'elapsed_seconds' => 'integer',
        'elevation_gain_meters' => 'integer',
        'average_pace_seconds_per_km' => 'float',
        'splits' => 'array',
        'best_efforts' => 'array',
        'version' => 'integer',
    ];
}
