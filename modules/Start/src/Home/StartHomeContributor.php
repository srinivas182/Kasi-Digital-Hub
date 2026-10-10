<?php

declare(strict_types=1);

namespace Modules\Start\Home;

use Modules\Core\Enterprise\SupportOffers;
use Modules\Core\Home\HomeContributor;
use Modules\Core\Home\NextStep;
use Modules\Core\Identity\Models\User;
use Modules\Start\Services\Businesses;

/** "Your business" on the hub home, with its next formalisation step. */
final readonly class StartHomeContributor implements HomeContributor
{
    public function __construct(private Businesses $businesses) {}

    public function nextSteps(User $user): array
    {
        $business = $this->businesses->of($user)->first();
        if ($business === null) {
            return [];
        }
        $steps = [];
        $offers = app()->bound(SupportOffers::class) ? app(SupportOffers::class)->eligibleCount($business->id) : 0;
        if ($offers > 0) {
            $steps[] = new NextStep('start_support', __('start.step.support', ['count' => $offers]), __('start.step.support_hint'), '/support?business='.$business->id, false, 45);
        }
        if ($business->formalised_at !== null) {
            return $steps;
        }
        $next = $this->businesses->readiness($business)['next'];

        return [...$steps, new NextStep('start_business', __('start.step.business', ['name' => $business->name]), $next !== null ? __('start.step.next', ['step' => __('start.next.'.$next)]) : __('start.step.plan'),
            '/start/businesses/'.$business->id, false, 46)];
    }
}
