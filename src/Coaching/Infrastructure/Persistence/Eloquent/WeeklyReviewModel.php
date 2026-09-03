<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $week_start
 */
final class WeeklyReviewModel extends Model
{
    protected $table = 'weekly_reviews';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id', 'tenant_id', 'week_start', 'messages', 'version',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'messages' => 'array',
        'version' => 'integer',
    ];
}
