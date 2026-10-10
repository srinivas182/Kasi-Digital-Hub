<?php

declare(strict_types=1);

namespace Modules\Start\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string|null $sells
 * @property string $sector
 * @property string $stage
 * @property string|null $legal_form
 * @property int|null $municipality_id
 * @property string|null $place_name
 * @property string|null $hub_id
 * @property int $people
 * @property string|null $turnover_band
 * @property string|null $customers
 * @property int $readiness
 * @property CarbonImmutable|null $formalised_at
 */
final class Business extends Model
{
    use HasUlids;

    public const SECTORS = ['food', 'retail', 'beauty', 'services', 'construction', 'agriculture', 'transport', 'manufacturing', 'creative', 'digital', 'care', 'other'];

    public const STAGES = ['idea', 'informal', 'registered'];

    public const FORMS = ['sole', 'pty', 'coop', 'npc'];

    public const TURNOVER = ['none', 'under_5k', '5k_20k', '20k_80k', 'over_80k'];

    protected $table = 'start_businesses';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['people' => 'integer', 'readiness' => 'integer', 'formalised_at' => 'immutable_datetime'];
    }
}
