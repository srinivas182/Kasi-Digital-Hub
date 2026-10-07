<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Authenticator-app second step for staff, organisation and national roles.
 *
 * @property string $user_id
 * @property string $secret
 * @property list<string> $recovery_codes
 * @property CarbonImmutable|null $confirmed_at
 */
final class StaffTwoFactor extends Model
{
    protected $table = 'staff_two_factor';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'secret', 'recovery_codes', 'confirmed_at'];

    protected $hidden = ['secret', 'recovery_codes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_codes' => 'array',
            'confirmed_at' => 'immutable_datetime',
        ];
    }
}
