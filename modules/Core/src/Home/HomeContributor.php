<?php

declare(strict_types=1);

namespace Modules\Core\Home;

use Modules\Core\Identity\Models\User;

/**
 * Portal SDK extension point for the hub home. A portal adds next steps (e.g. KasiWork:
 * "Complete your CV") and widgets without touching shared code: implement this interface
 * and tag the class in the module's service provider:
 *
 *     $this->app->tag([WorkHomeContributor::class], HomeRegistry::TAG);
 */
interface HomeContributor
{
    /** @return list<NextStep> */
    public function nextSteps(User $user): array;
}
