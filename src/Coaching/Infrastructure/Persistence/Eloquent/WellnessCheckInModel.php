<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $check_date
 * @property int $sleep
 * @property int $energy
 * @property int $legs
 * @property int $motivation
 * @property int $pain_level
 * @property string $pain_location
 * @property string $note
 */
final class WellnessCheckInModel extends Model
{
    protected $table = 'wellness_check_ins';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id', 'tenant_id', 'check_date', 'sleep', 'energy', 'legs', 'motivation', 'pain_level', 'pain_location', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sleep' => 'integer',
        'energy' => 'integer',
        'legs' => 'integer',
        'motivation' => 'integer',
        'pain_level' => 'integer',
    ];
}
