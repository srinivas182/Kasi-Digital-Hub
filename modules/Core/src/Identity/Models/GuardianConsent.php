<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A parent or guardian's consent for a 16-17 year-old, verified by a code sent to the guardian's phone.
 *
 * @property string $id
 * @property string $user_id
 * @property string $guardian_name
 * @property string $guardian_phone
 * @property string $relationship
 * @property CarbonImmutable|null $verified_at
 */
final class GuardianConsent extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'guardian_name', 'guardian_phone', 'relationship', 'verified_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime'];
    }
}
