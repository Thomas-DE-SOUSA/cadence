<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $session_date
 * @property string $title
 * @property string $note
 * @property int|null $duration_seconds
 * @property int $version
 * @property array<int, array<string, mixed>> $exercises
 */
final class StrengthSessionModel extends Model
{
    use SoftDeletes;

    protected $table = 'strength_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['id', 'tenant_id', 'session_date', 'title', 'note', 'duration_seconds', 'version', 'exercises'];

    /** @var array<string, string> */
    protected $casts = [
        'exercises' => 'array',
        'duration_seconds' => 'integer',
        'version' => 'integer',
    ];
}
