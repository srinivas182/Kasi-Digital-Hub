<?php

declare(strict_types=1);

namespace Modules\Core\Search;

use Modules\Core\Identity\Models\User;

/**
 * Indexing and searching across portals, with results limited to what the person may see.
 */
final readonly class SearchService
{
    public function __construct(private SearchEngine $engine, private SearchRegistry $sources) {}

    /** Bring one item up to date (called from a queued job when content changes). */
    public function refresh(string $type, string $ref): void
    {
        $document = $this->sources->get($type)->find($ref);
        $document === null ? $this->engine->delete($type, $ref) : $this->engine->upsert([$document]);
    }

    /** @return array<string, int> type => documents indexed */
    public function reindex(?string $only = null): array
    {
        $counts = [];
        foreach ($this->sources->all() as $type => $source) {
            if ($only !== null && $only !== $type) {
                continue;
            }
            $this->engine->flush($type);
            $batch = [];
            $counts[$type] = 0;
            foreach ($source->all() as $document) {
                $batch[] = $document;
                $counts[$type]++;
                if (count($batch) === 200) {
                    $this->engine->upsert($batch);
                    $batch = [];
                }
            }
            $this->engine->upsert($batch);
        }

        return $counts;
    }

    /** @return list<array{type: string, ref: string, title: string, body: string, url: string}> */
    public function search(string $query, ?User $user, int $limit = 30): array
    {
        return $this->engine->search($query, self::visibilitiesFor($user), $limit);
    }

    /** @return list<string> */
    public static function visibilitiesFor(?User $user): array
    {
        return match (true) {
            $user === null => ['public'],
            $user->isMinor() => ['public', 'learners'],
            default => ['public', 'members', 'learners'],
        };
    }
}
