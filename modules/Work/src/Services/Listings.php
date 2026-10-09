<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Work\Events\ListingClosed;
use Modules\Work\Events\ListingPublished;
use Modules\Work\Matching\Invitations;
use Modules\Work\Models\JobListing;
use Modules\Work\Notifications\Messages\ListingStatusNotification;

/**
 * Listing lifecycle: draft -> (review) -> live -> closed / filled / expired / taken down.
 * Only verified employers publish; flagged listings wait for a person in the content review queue.
 */
final readonly class Listings
{
    public function __construct(
        private ListingChecks $checks,
        private ModerationService $moderation,
        private Notifier $notifier,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{name: string, must: bool}>  $skills
     * @param  list<array{question: string, kind: string}>  $questions
     */
    public function save(Organisation $employer, array $data, array $skills, array $questions, User $by, ?JobListing $listing = null): JobListing
    {
        $listing = DB::transaction(function () use ($employer, $data, $skills, $questions, $by, $listing): JobListing {
            $listing ??= new JobListing(['organisation_id' => $employer->id, 'created_by' => $by->id, 'status' => 'draft']);
            $listing->fill($data)->save();

            $listing->skills()->delete();
            foreach ($skills as $skill) {
                $listing->skills()->create(['name' => mb_substr(trim($skill['name']), 0, 60), 'must' => $skill['must']]);
            }
            $listing->questions()->delete();
            foreach ($questions as $i => $question) {
                $listing->questions()->create(['question' => trim($question['question']), 'kind' => $question['kind'], 'position' => $i]);
            }

            return $listing;
        });

        $this->audit->record('work.listing_saved', meta: ['listing' => $listing->id, 'status' => $listing->status], actor: $by);

        // Changing a live listing runs the checks again.
        if (in_array($listing->status, ['live', 'review'], true)) {
            $this->publish($listing, $by);
        }

        return $listing;
    }

    public function publish(JobListing $listing, User $by): JobListing
    {
        $employer = $listing->organisation;

        if (! $employer->isVerified()) {
            throw new DomainException(__('work.listing.not_verified'));
        }

        $limit = (int) config($employer->community ? 'kasi.work.community_listing_limit' : 'kasi.work.free_listing_limit');
        $active = JobListing::query()->where('organisation_id', $employer->id)->whereIn('status', JobListing::ACTIVE)->whereKeyNot($listing->id)->count();
        if ($active >= $limit) {
            throw new DomainException(__('work.listing.limit', ['limit' => $limit]));
        }

        $today = CarbonImmutable::now('Africa/Johannesburg')->startOfDay();
        if ($listing->closes_on->lt($today) || $listing->closes_on->gt($today->addDays((int) config('kasi.work.max_listing_days')))) {
            throw new DomainException(__('work.listing.closing_date', ['days' => config('kasi.work.max_listing_days')]));
        }

        $text = $this->textOf($listing);
        $result = $this->moderation->check($text, 'job_listing', $listing->id, $by, extraReasons: $this->checks->reviewReasons($text));

        if ($result['verdict'] === 'flag') {
            $listing->forceFill(['status' => 'review', 'status_reason' => implode('; ', $result['reasons'])])->save();
            $this->tellEmployer($listing, 'review');
        } else {
            $wasLive = $listing->status === 'live';
            $listing->forceFill(['status' => 'live', 'status_reason' => null, 'published_at' => $listing->published_at ?? now()])->save();
            if (! $wasLive) {
                event(new ListingPublished($listing, $by->id));
            }
        }

        $this->audit->record('work.listing_'.$listing->status, meta: ['listing' => $listing->id], actor: $by);

        return $listing;
    }

    /** @param 'closed'|'filled'|'expired'|'taken_down' $status */
    public function close(JobListing $listing, string $status, ?User $by = null, ?string $reason = null): void
    {
        $listing->forceFill(['status' => $status, 'status_reason' => $reason, 'closed_at' => now()])->save();
        $this->audit->record('work.listing_'.$status, meta: ['listing' => $listing->id, 'reason' => $reason], actor: $by);
        app(Invitations::class)->expireFor($listing->id);
        event(new ListingClosed($listing, $by?->id));

        if ($status === 'taken_down') {
            $this->tellEmployer($listing, 'taken_down');
        }
    }

    /** A reviewer decided on a flagged listing (core.moderation.decided). */
    public function reviewed(JobListing $listing, string $decision, string $reason): void
    {
        if ($decision === 'approved' && $listing->status === 'review') {
            $listing->forceFill(['status' => 'live', 'status_reason' => null, 'published_at' => $listing->published_at ?? now()])->save();
            event(new ListingPublished($listing));
            $this->tellEmployer($listing, 'live');
        }

        if ($decision === 'rejected' && in_array($listing->status, ['review', 'live'], true)) {
            $this->close($listing, 'taken_down', null, $reason);
        }
    }

    /**
     * Daily: expire listings past their closing date; remind employers 3 days before.
     *
     * @return array{expired: int, reminded: int}
     */
    public function housekeeping(): array
    {
        $today = CarbonImmutable::now('Africa/Johannesburg')->toDateString();
        $expired = 0;
        $reminded = 0;

        JobListing::query()->where('status', 'live')->whereDate('closes_on', '<', $today)->each(function (JobListing $l) use (&$expired): void {
            $this->close($l, 'expired');
            $expired++;
        });

        JobListing::query()->where('status', 'live')->whereNull('reminded_at')
            ->whereDate('closes_on', '<=', CarbonImmutable::now('Africa/Johannesburg')->addDays(3)->toDateString())
            ->each(function (JobListing $l) use (&$reminded): void {
                $this->tellEmployer($l, 'expiring');
                $l->forceFill(['reminded_at' => now()])->save();
                $reminded++;
            });

        return ['expired' => $expired, 'reminded' => $reminded];
    }

    public function textOf(JobListing $listing): string
    {
        $listing->loadMissing('questions', 'skills');

        return trim($listing->title."\n".$listing->description."\n".($listing->hours ?? '')."\n"
            .$listing->skills->pluck('name')->implode(', ')."\n".$listing->questions->pluck('question')->implode("\n"));
    }

    /** @param 'review'|'live'|'taken_down'|'expiring' $state */
    private function tellEmployer(JobListing $listing, string $state): void
    {
        $adminIds = RoleAssignment::query()->where('role', 'employer_admin')->where('scope_type', 'organisation')->where('scope_id', $listing->organisation_id)->pluck('user_id');
        User::query()->whereIn('id', $adminIds->push($listing->created_by)->filter()->unique())->get()
            ->each(fn (User $user) => $this->notifier->send($user, new ListingStatusNotification($listing, $state)));
    }
}
