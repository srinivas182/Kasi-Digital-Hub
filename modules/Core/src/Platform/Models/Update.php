<?php

declare(strict_types=1);

namespace Modules\Core\Platform\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * An item in a person's "what changed and why" feed (also the in-app notification channel).
 *
 * @property string $id
 * @property string $user_id
 * @property string $module
 * @property string $category
 * @property string $title
 * @property string|null $body
 * @property string|null $cause
 * @property string|null $url
 * @property string|null $event_id
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable $created_at
 */
final class Update extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'module', 'category', 'title', 'body', 'cause', 'url', 'event_id', 'read_at', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
