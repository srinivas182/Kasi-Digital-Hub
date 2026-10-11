<?php

declare(strict_types=1);

namespace Modules\Connect\Home;

use Modules\Connect\Models\Mentorship;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Home\HomeContributor;
use Modules\Core\Home\NextStep;
use Modules\Core\Identity\Models\User;

/** "Your mentor" (next session), or "Get a mentor" for people running a business, on the hub home. */
final class ConnectHomeContributor implements HomeContributor
{
    public function nextSteps(User $user): array
    {
        $m = Mentorship::query()->where('mentee_id', $user->id)->whereIn('status', ['proposed', 'active'])->latest('updated_at')->first();
        if ($m !== null) {
            return [new NextStep('connect_mentor', __('connect.step.mentor'), __('connect.step.mentor_hint'), '/connect/mentorships/'.$m->id, false, 30)];
        }

        // Suggested when a mentor could help with a current goal: running a business (S18 spec).
        $facts = app()->bound(BusinessFacts::class) ? app(BusinessFacts::class) : null;
        if ($facts === null || $facts->businessesOf($user) === []) {
            return [];
        }

        return [new NextStep('connect_find', __('connect.step.find'), __('connect.step.find_hint'), '/connect/find?goal=business', false, 70)];
    }
}
