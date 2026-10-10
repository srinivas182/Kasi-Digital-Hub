<?php

declare(strict_types=1);

namespace Modules\Partner\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $offer_id
 * @property string $organisation_id
 * @property string $business_id
 * @property string|null $sent_by
 * @property string|null $assisted_by
 * @property string|null $facilitator_note
 * @property array{profile?: bool, summary?: bool, readiness?: bool, documents?: list<string>} $shared
 * @property string|null $message
 * @property string $stage
 * @property string|null $decline_reason
 * @property string|null $outcome
 * @property int|null $outcome_value_cents
 * @property CarbonImmutable|null $outcome_confirmed_at
 * @property CarbonImmutable|null $first_response_at
 * @property CarbonImmutable|null $reminded_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable $created_at
 * @property-read Offer $offer
 */
final class Referral extends Model
{
    use HasUlids;

    public const OPEN = ['new', 'reviewing', 'info', 'approved'];

    public const DECLINE_REASONS = ['not_eligible', 'incomplete', 'capacity', 'not_viable', 'other'];

    protected $table = 'partner_referrals';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['shared' => 'array', 'outcome_value_cents' => 'integer', 'outcome_confirmed_at' => 'immutable_datetime', 'first_response_at' => 'immutable_datetime', 'reminded_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Offer, $this> */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /** Partners may open shared documents until the referral closes plus 90 days. */
    public function partnerCanSeeDocuments(): bool
    {
        return $this->stage !== 'withdrawn' && ($this->closed_at === null || $this->closed_at->gt(now()->subDays(90)));
    }
}
