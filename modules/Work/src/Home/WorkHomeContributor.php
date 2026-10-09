<?php

declare(strict_types=1);

namespace Modules\Work\Home;

use Illuminate\Support\Facades\DB;
use Modules\Core\Home\HomeContributor;
use Modules\Core\Home\NextStep;
use Modules\Core\Identity\Models\User;
use Modules\Work\Models\WorkCv;
use Modules\Work\Models\WorkProfile;

/** KasiWork's next steps on the hub home (adults only). */
final class WorkHomeContributor implements HomeContributor
{
    public function nextSteps(User $user): array
    {
        if ($user->isMinor()) {
            return [];
        }

        $profile = WorkProfile::query()->find($user->id);

        $newMatches = DB::table('work_matches')->where('user_id', $user->id)->where('score', '>=', 70)
            ->where('computed_at', '>=', now()->subDays(7))->count();

        return [
            ...($newMatches > 0 ? [new NextStep('work_matches', __('work.step.matches', ['count' => $newMatches]), __('work.step.matches_hint'), '/work/matches', false, 5)] : []),
            new NextStep('work_profile', __('work.step.profile'), __('work.step.profile_hint', ['percent' => $profile->completeness ?? 0]), '/work/profile', $profile?->completed_at !== null, 45),
            new NextStep('work_cv', __('work.step.cv'), __('work.step.cv_hint'), '/work/cv', WorkCv::query()->where('user_id', $user->id)->exists(), 46),
        ];
    }
}
