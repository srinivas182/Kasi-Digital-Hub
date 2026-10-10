<?php

declare(strict_types=1);

namespace Modules\Learn\Home;

use Modules\Core\Home\HomeContributor;
use Modules\Core\Home\NextStep;
use Modules\Core\Identity\Models\User;
use Modules\Learn\Services\CourseSuggestions;

/** KasiLearn next step on the hub home: courses that fill skills gaps. */
final readonly class LearnHomeContributor implements HomeContributor
{
    public function __construct(private CourseSuggestions $suggestions) {}

    public function nextSteps(User $user): array
    {
        $count = $this->suggestions->for($user)->count();

        return $count === 0 ? [] : [new NextStep('learn_courses', __('learn.step.courses', ['count' => $count]), __('learn.step.courses_hint'), '/learn/courses', false, 47)];
    }
}
