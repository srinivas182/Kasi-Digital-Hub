<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Generation;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Identity\Models\User;

/**
 * A PDF the platform issued (CV, certificate, letter, report) with a public verification code.
 *
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property int $template_version
 * @property string $title
 * @property string $disk
 * @property string $path
 * @property string $sha256
 * @property string $verification_code
 * @property bool $show_name
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoke_reason
 * @property-read User $owner
 */
final class GeneratedDocument extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['show_name' => 'boolean', 'issued_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'template_version' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** valid | revoked | expired */
    public function status(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->expires_at !== null && $this->expires_at->isPast() => 'expired',
            default => 'valid',
        };
    }
}
