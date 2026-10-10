<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $enrolment_id
 * @property string $lesson_id
 * @property string|null $text
 * @property string|null $document_id
 * @property string $status
 * @property int $attempt
 * @property list<string>|null $rubric
 * @property string|null $feedback
 * @property string|null $assessor_id
 * @property CarbonImmutable|null $assessed_at
 * @property CarbonImmutable $created_at
 * @property-read Enrolment $enrolment
 */
final class Submission extends Model
{
    use HasUlids;

    protected $table = 'learn_submissions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rubric' => 'array', 'attempt' => 'integer', 'assessed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Enrolment, $this> */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }
}
