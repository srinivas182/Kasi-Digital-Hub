<?php

declare(strict_types=1);

namespace Modules\Learn\Search;

use Modules\Core\Search\SearchDocument;
use Modules\Core\Search\SearchSource;
use Modules\Learn\Models\Course;

/** Published courses: 16+ courses are public; 18+ courses are for signed-in adults. */
final class CourseSearchSource implements SearchSource
{
    public function type(): string
    {
        return 'course';
    }

    public function all(): iterable
    {
        foreach (Course::query()->where('status', 'published')->with('organisation')->cursor() as $course) {
            yield $this->document($course);
        }
    }

    public function find(string $ref): ?SearchDocument
    {
        $course = Course::query()->where('status', 'published')->with('organisation')->whereKey($ref)->first();

        return $course === null ? null : $this->document($course);
    }

    private function document(Course $c): SearchDocument
    {
        return new SearchDocument('course', $c->id, $c->title.' - '.$c->organisation->displayName(),
            trim($c->summary.' '.implode(' ', $c->outcomes ?? [])), '/learn/courses/'.$c->slug, $c->min_age < 18 ? 'public' : 'members');
    }
}
