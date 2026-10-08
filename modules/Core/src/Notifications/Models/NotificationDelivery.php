<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Identity\Models\User;

/**
 * One message on one channel: its status, provider reference, error and estimated cost.
 *
 * @property string $id
 * @property string $user_id
 * @property string $notification
 * @property string $category
 * @property string $channel
 * @property string $status
 * @property string|null $skip_reason
 * @property string|null $dedupe_key
 * @property array{title: string, body: string, url: string|null, important: bool, to: string|null, template: string|null, params: list<string>} $payload
 * @property CarbonImmutable|null $scheduled_for
 * @property CarbonImmutable|null $sent_at
 * @property string|null $provider_reference
 * @property string|null $error
 * @property int $cost_cents
 * @property string|null $fallback_for
 * @property CarbonImmutable $created_at
 */
final class NotificationDelivery extends Model
{
    use HasUlids;

    public const QUEUED = 'queued';

    public const HELD = 'held';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    protected $fillable = [
        'user_id', 'notification', 'category', 'channel', 'status', 'skip_reason', 'dedupe_key', 'payload',
        'scheduled_for', 'sent_at', 'provider_reference', 'error', 'cost_cents', 'fallback_for',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'scheduled_for' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'cost_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
