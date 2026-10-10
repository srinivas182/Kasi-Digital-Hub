<?php

declare(strict_types=1);

namespace Modules\Learn\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Home\SkillGapSource;
use Modules\Core\Identity\Models\User;
use Modules\Learn\Models\Course;

/**
 * "Courses for you": published courses whose title, summary or outcomes mention a skill the person
 * is missing (from SkillGapSource, e.g. KasiWork matches). Simple and explainable.
 */
final class CourseSuggestions
{
    /** @return Collection<int, Course> */
    public function for(User $user, int $limit = 3): Collection
    {
        $gaps = [];
        foreach (app()->tagged(SkillGapSource::TAG) as $source) {
            /** @var SkillGapSource $source */
            array_push($gaps, ...$source->gaps($user));
        }
        $gaps = array_slice(array_values(array_unique($gaps)), 0, 8);
        if ($gaps === []) {
            return new Collection;
        }

        return Course::query()->with(['organisation', 'currentVersion', 'accreditation'])->where('status', 'published')->whereNotNull('current_version_id')
            ->when($user->isMinor(), fn (Builder $q) => $q->where('min_age', '<', 18))
            ->where(function (Builder $q) use ($gaps): void {
                foreach ($gaps as $gap) {
                    $q->orWhere('title', 'like', "%{$gap}%")->orWhere('summary', 'like', "%{$gap}%")->orWhere('outcomes', 'like', "%{$gap}%");
                }
            })->limit($limit)->get();
    }
}
