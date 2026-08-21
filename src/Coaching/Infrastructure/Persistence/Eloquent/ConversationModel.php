<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $program_id
 * @property string $cycle_id
 * @property string $session_date
 */
final class ConversationModel extends Model
{
    protected $table = 'conversations';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id', 'tenant_id', 'program_id', 'cycle_id', 'session_date', 'messages', 'version',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'messages' => 'array',
        'version' => 'integer',
    ];
}
