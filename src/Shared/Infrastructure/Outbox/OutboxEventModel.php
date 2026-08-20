<?php

declare(strict_types=1);

namespace Cadence\Shared\Infrastructure\Outbox;

use Illuminate\Database\Eloquent\Model;

/**
 * Durable record of a domain event, written in the same transaction as its
 * aggregate. A separate publisher drains unpublished rows.
 */
final class OutboxEventModel extends Model
{
    protected $table = 'outbox_events';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'published' => 'boolean',
        'version' => 'integer',
    ];
}
