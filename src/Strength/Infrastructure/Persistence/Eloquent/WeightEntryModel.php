<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $logged_date
 * @property string $moment
 * @property float $weight_kg
 * @property string $note
 */
final class WeightEntryModel extends Model
{
    protected $table = 'weight_entries';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['id', 'tenant_id', 'logged_date', 'moment', 'weight_kg', 'note'];

    /** @var array<string, string> */
    protected $casts = [
        'weight_kg' => 'float',
    ];
}
