<?php

declare(strict_types=1);

namespace Cadence\Athlete\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 */
final class AthleteModel extends Model
{
    use SoftDeletes;

    protected $table = 'athlete_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'id', 'tenant_id', 'profile', 'created_at', 'version',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'profile' => 'array',
        'version' => 'integer',
    ];
}
