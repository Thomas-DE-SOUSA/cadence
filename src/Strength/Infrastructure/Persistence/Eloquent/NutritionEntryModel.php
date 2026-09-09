<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $logged_date
 * @property string $meal
 * @property string $description
 * @property int $kcal
 * @property int $protein_g
 * @property int $fat_g
 * @property int $carbs_g
 */
final class NutritionEntryModel extends Model
{
    protected $table = 'nutrition_entries';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['id', 'tenant_id', 'logged_date', 'meal', 'description', 'kcal', 'protein_g', 'fat_g', 'carbs_g'];

    /** @var array<string, string> */
    protected $casts = [
        'kcal' => 'integer',
        'protein_g' => 'integer',
        'fat_g' => 'integer',
        'carbs_g' => 'integer',
    ];
}
