<?php

declare(strict_types=1);

namespace Modules\Work\Matching;

use Illuminate\Support\Facades\DB;
use Modules\Work\Models\JobListing;

/**
 * Keeps `work_matches` up to date: per person (after their profile changes), per advert (after it
 * changes) and nightly for everyone. Only matches of 30% or more are kept.
 */
final readonly class MatchIndex
{
    public const KEEP_FROM = 30;

    public function __construct(private MatchFacts $facts, private Matcher $matcher) {}

    public function refreshSeeker(string $userId): int
    {
        $seeker = $this->facts->seeker($userId);
        if ($seeker === null) {
            DB::table('work_matches')->where('user_id', $userId)->delete();

            return 0;
        }

        $kept = [];
        foreach (JobListing::query()->live()->with('skills', 'organisation')->cursor() as $listing) {
            if ($this->store($seeker, $this->facts->listing($listing))) {
                $kept[] = $listing->id;
            }
        }
        DB::table('work_matches')->where('user_id', $userId)->whereNotIn('listing_id', $kept)->delete();

        return count($kept);
    }

    public function refreshListing(string $listingId): int
    {
        $listing = JobListing::query()->with('skills', 'organisation')->find($listingId);
        if ($listing === null || $listing->status !== 'live') {
            DB::table('work_matches')->where('listing_id', $listingId)->delete();

            return 0;
        }

        $facts = $this->facts->listing($listing);
        $kept = [];
        foreach (DB::table('work_profiles')->pluck('user_id') as $userId) {
            $seeker = $this->facts->seeker((string) $userId);
            if ($seeker !== null && $this->store($seeker, $facts)) {
                $kept[] = (string) $userId;
            }
        }
        DB::table('work_matches')->where('listing_id', $listingId)->whereNotIn('user_id', $kept)->delete();

        return count($kept);
    }

    /** Nightly: everyone against every live advert. */
    public function refreshAll(): int
    {
        DB::table('work_matches')->whereNotIn('listing_id', JobListing::query()->live()->select('id'))->delete();
        $count = 0;
        foreach (DB::table('work_profiles')->pluck('user_id') as $userId) {
            $count += $this->refreshSeeker((string) $userId);
        }

        return $count;
    }

    private function store(SeekerFacts $seeker, ListingFacts $listing): bool
    {
        $result = $this->matcher->score($seeker, $listing);
        if ($result->outOfRange || $result->score < self::KEEP_FROM) {
            return false;
        }

        $existing = DB::table('work_matches')->where('user_id', $seeker->userId)->where('listing_id', $listing->listingId)->first(['score', 'alerted_at']);
        DB::table('work_matches')->updateOrInsert(
            ['user_id' => $seeker->userId, 'listing_id' => $listing->listingId],
            [
                'score' => $result->score, 'reasons' => json_encode($result->reasons), 'gaps' => json_encode($result->gaps), 'has_gaps' => $result->gaps !== [],
                'distance_km' => $result->distanceKm, 'computed_at' => now(), 'alerted_at' => $existing?->alerted_at,
            ],
        );

        return true;
    }
}
