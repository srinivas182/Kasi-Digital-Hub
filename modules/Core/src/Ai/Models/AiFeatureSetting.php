<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin switch and monthly budget per AI feature ('*' = all AI).
 *
 * @property string $feature
 * @property bool $enabled
 * @property int|null $monthly_budget_cents
 * @property string|null $updated_by
 */
final class AiFeatureSetting extends Model
{
    public const ALL = '*';

    protected $primaryKey = 'feature';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'monthly_budget_cents' => 'integer'];
    }
}
