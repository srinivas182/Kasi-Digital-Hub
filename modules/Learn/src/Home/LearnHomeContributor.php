<?php

declare(strict_types=1);

namespace Modules\Learn\Home;

use Modules\Core\Home\HomeContributor;
use Modules\Core\Home\NextStep;
use Modules\Core\Identity\Models\User;
use Modules\Learn\Models\Enrolment;
use Modules\Learn\Services\CourseSuggestions;

/** KasiLearn next step on the hub home: courses that fill skills gaps. */
final readonly class LearnHomeContributor implements HomeContributor
{
    public function __construct(private CourseSuggestions $suggestions) {}

    public function nextSteps(User $user): array
    {
        $steps = [];
        $current = Enrolment::query()->with('course')->where('user_id', $user->id)->where('status', 'active')->latest('updated_at')->first();
        if ($current !== null) {
            $url = $current->last_lesson_id !== null ? "/learn/my/{$current->id}/lessons/{$current->last_lesson_id}" : "/learn/my/{$current->id}";
            $steps[] = new NextStep('learn_continue', __('learn.step.continue', ['title' => $current->course->title]), __('learn.step.continue_hint', ['progress' => $current->progress]), $url, false, 4);
        }

        $count = $this->suggestions->for($user)->count();
        if ($count > 0) {
            $steps[] = new NextStep('learn_courses', __('learn.step.courses', ['count' => $count]), __('learn.step.courses_hint'), '/learn/courses', false, 47);
        }

        return $steps;
    }
}
