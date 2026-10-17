<?php

declare(strict_types=1);

namespace Modules\Learn\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Insights\ReviewSource;

/** Courses waiting for KasiHub review (S13) - for the review inbox. */
final class CoursesInReview implements ReviewSource
{
    public function waiting(): array
    {
        return array_values(DB::table('learn_courses')->where('status', 'in_review')->orderBy('updated_at')->get(['id', 'title', 'min_age', 'updated_at'])
            ->map(static fn (object $c): array => ['type' => 'course', 'id' => (string) $c->id, 'title' => (string) $c->title,
                'reason' => (int) $c->min_age < 18 ? __('admin.inbox.course_minors') : __('admin.inbox.course_first'), 'since' => (string) $c->updated_at,
                'url' => '/learn/review/courses/'.$c->id, 'bulk' => false])->all());
    }
}
