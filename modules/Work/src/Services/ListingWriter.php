<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use Modules\Core\Ai\AiService;
use Modules\Core\Identity\Models\User;
use Modules\Work\Models\OfoOccupation;

/**
 * "Help me write it" for employers: rough notes in, suggested listing fields out (the employer
 * reviews everything). The occupation is matched to the OFO list, never invented.
 */
final readonly class ListingWriter
{
    public function __construct(private AiService $ai, private ListingChecks $checks) {}

    /** @return array<string, mixed> */
    public function suggest(string $notes, User $by, ?string $hubId = null): array
    {
        $result = $this->ai->run('work.listing_writer', ['notes' => $notes], by: $by, hubId: $hubId);
        if (! $result->ok) {
            return ['ok' => false, 'reason' => $result->reason];
        }

        $list = static fn (mixed $v, int $max, int $length = 60): array => array_slice(array_values(array_filter(array_map(static fn ($s): string => mb_substr(trim((string) $s), 0, $length), (array) $v))), 0, $max);
        $questions = array_values(array_filter($list($result->data['questions'] ?? [], 3, 200), fn (string $q): bool => $this->checks->questionProblem($q) === null));

        return [
            'ok' => true,
            'title' => mb_substr(trim((string) ($result->data['title'] ?? '')), 0, 120),
            'occupation' => $this->matchOccupation((string) ($result->data['occupation'] ?? $result->data['title'] ?? '')),
            'description' => trim((string) ($result->data['description'] ?? '')),
            'mustSkills' => $list($result->data['must_skills'] ?? [], 5),
            'niceSkills' => $list($result->data['nice_skills'] ?? [], 5),
            'questions' => $questions,
        ];
    }

    /** @return array{id: int, title: string}|null */
    public function matchOccupation(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $match = OfoOccupation::query()->where('title', $name)->first()
            ?? OfoOccupation::query()->where('title', 'like', $name.'%')->orderByRaw('length(title)')->first()
            ?? OfoOccupation::query()->where('title', 'like', '%'.$name.'%')->orderByRaw('length(title)')->first();

        return $match === null ? null : ['id' => $match->id, 'title' => $match->title];
    }
}
