<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Identity\Models\User;

/**
 * A file in a person's document vault. Verified once, reused by every portal.
 *
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property string $status
 * @property string|null $rejection_reason
 * @property string|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $expires_on
 * @property string|null $uploaded_by
 * @property CarbonImmutable|null $expiry_reminded_at
 * @property CarbonImmutable $created_at
 */
final class Document extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const PENDING_SCAN = 'pending_scan';

    public const UPLOADED = 'uploaded';

    public const VERIFIED = 'verified';

    public const REJECTED = 'rejected';

    public const QUARANTINED = 'quarantined';

    protected $fillable = [
        'user_id', 'type', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'status',
        'rejection_reason', 'verified_by', 'verified_at', 'expires_on', 'uploaded_by', 'expiry_reminded_at',
    ];

    protected $hidden = ['disk', 'path', 'sha256'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'verified_at' => 'immutable_datetime',
            'expires_on' => 'immutable_date',
            'expiry_reminded_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function canBeOpened(): bool
    {
        return in_array($this->status, [self::UPLOADED, self::VERIFIED, self::REJECTED], true);
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<DocumentShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class);
    }
}
