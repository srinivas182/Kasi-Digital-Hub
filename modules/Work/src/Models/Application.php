<?php

declare(strict_types=1);

namespace Modules\Work\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Identity\Models\User;

/**
 * @property string $id
 * @property string $listing_id
 * @property string|null $user_id
 * @property string|null $cv_id
 * @property string $stage
 * @property list<array{question: string, answer: string}>|null $answers
 * @property string|null $message
 * @property int|null $score
 * @property string $reference
 * @property string|null $assisted_by
 * @property string|null $reject_reason
 * @property CarbonImmutable|null $hired_on
 * @property CarbonImmutable|null $hire_confirmed_at
 * @property CarbonImmutable|null $outcome_notified_at
 * @property CarbonImmutable|null $stage_changed_at
 * @property CarbonImmutable|null $anonymised_at
 * @property CarbonImmutable $created_at
 * @property-read JobListing $listing
 * @property-read User|null $user
 * @property-read WorkCv|null $cv
 */
final class Application extends Model
{
    use HasUlids;

    public const STAGES = ['new', 'shortlisted', 'interview', 'offer', 'hired', 'unsuccessful'];

    /** Stages from which names are shown even with blind shortlisting. */
    public const NAMED_STAGES = ['interview', 'offer', 'hired'];

    public const OPEN = ['new', 'shortlisted', 'interview', 'offer'];

    public const REJECT_REASONS = ['experience', 'skills', 'availability', 'distance', 'position_filled', 'other'];

    protected $table = 'work_applications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'answers' => 'array', 'hired_on' => 'immutable_date', 'hire_confirmed_at' => 'immutable_datetime', 'outcome_notified_at' => 'immutable_datetime',
            'stage_changed_at' => 'immutable_datetime', 'anonymised_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'score' => 'integer',
        ];
    }

    /** @return BelongsTo<JobListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'listing_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<WorkCv, $this> */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(WorkCv::class, 'cv_id');
    }

    /** Names, phone and CV are hidden while blind shortlisting applies. */
    public function blind(): bool
    {
        return $this->listing->blind_shortlisting && ! in_array($this->stage, self::NAMED_STAGES, true);
    }
}
