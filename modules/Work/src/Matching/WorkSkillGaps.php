<?php

declare(strict_types=1);

namespace Modules\Work\Matching;

use Illuminate\Support\Facades\DB;
use Modules\Core\Home\SkillGapSource;
use Modules\Core\Identity\Models\User;

/** Must-have skills of the person's best-matching jobs that they don't have yet. */
final readonly class WorkSkillGaps implements SkillGapSource
{
    public function __construct(private MatchFacts $facts) {}

    public function gaps(User $user): array
    {
        $listingIds = DB::table('work_matches')->where('user_id', $user->id)->orderByDesc('score')->limit(10)->pluck('listing_id');
        if ($listingIds->isEmpty()) {
            return [];
        }

        $have = $this->facts->canonical(DB::table('work_skills')->where('user_id', $user->id)->pluck('name')->all());
        $wanted = $this->facts->canonical(DB::table('work_listing_skills')->whereIn('listing_id', $listingIds)->where('must', true)->pluck('name')->all());

        return array_values(array_diff($wanted, $have));
    }
}
