<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Identity\Models\User;

/**
 * Content waiting for a person to review (job adverts, posts, public event text...).
 *
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string|null $author_id
 * @property string $excerpt
 * @property list<string> $reasons
 * @property string $source
 * @property string $status
 * @property string|null $reviewed_by
 * @property string|null $decision_reason
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable $created_at
 * @property-read User|null $author
 */
final class ModerationFlag extends Model
{
    use HasUlids;

    public const STATUSES = ['pending', 'approved', 'rejected', 'escalated'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['reasons' => 'array', 'reviewed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
