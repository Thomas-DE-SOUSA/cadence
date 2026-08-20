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

    /** @var list<string> */
    protected $fillable = [
        'id',
        'aggregate_id',
        'aggregate_type',
        'tenant_id',
        'event_name',
        'payload',
        'user_id',
        'version',
        'occurred_at',
        'published',
        'published_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'published' => 'boolean',
        'version' => 'integer',
    ];
}
