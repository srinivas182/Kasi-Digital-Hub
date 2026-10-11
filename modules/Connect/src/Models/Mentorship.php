<?php

declare(strict_types=1);

namespace Modules\Connect\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $mentor_id
 * @property string $mentee_id
 * @property string $goal
 * @property list<string> $goals
 * @property int $per_month
 * @property int $months
 * @property string $format
 * @property string $status
 * @property CarbonImmutable|null $mentor_agreed_at
 * @property CarbonImmutable|null $mentee_agreed_at
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property bool $renewed
 * @property string|null $ended_reason
 * @property list<bool>|null $goals_met
 * @property string|null $mentee_feedback
 * @property string|null $mentor_feedback
 */
final class Mentorship extends Model
{
    use HasUlids;

    protected $table = 'connect_mentorships';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['goals' => 'array', 'goals_met' => 'array', 'per_month' => 'integer', 'months' => 'integer', 'renewed' => 'boolean', 'mentor_agreed_at' => 'immutable_datetime', 'mentee_agreed_at' => 'immutable_datetime', 'starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }

    public function involves(string $userId): bool
    {
        return $this->mentor_id === $userId || $this->mentee_id === $userId;
    }
}
