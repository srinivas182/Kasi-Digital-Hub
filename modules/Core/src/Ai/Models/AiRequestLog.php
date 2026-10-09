<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\BelongsToHub;

/**
 * One AI call (audit + cost). Inputs/outputs are cleared after the retention period.
 *
 * @property string $id
 * @property string $feature
 * @property string $prompt_key
 * @property int $prompt_version
 * @property string $model
 * @property string $tier
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cost_cents
 * @property int $duration_ms
 * @property string $outcome
 * @property string|null $user_id
 * @property string|null $actor_id
 * @property string|null $hub_id
 * @property string|null $input
 * @property string|null $output
 * @property CarbonImmutable $created_at
 */
final class AiRequestLog extends Model
{
    use BelongsToHub;
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'ai_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
