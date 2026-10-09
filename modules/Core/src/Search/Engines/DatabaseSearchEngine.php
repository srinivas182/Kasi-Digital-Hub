<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Illuminate\Support\Facades\DB;
use Modules\Core\Search\SearchEngine;

/**
 * Search in the database: word matches first, then forgiving one-letter typos
 * ("Gyani" finds "Giyani"). Fine for the few thousand public items of a pilot.
 */
final class DatabaseSearchEngine implements SearchEngine
{
    public function upsert(array $documents): void
    {
        foreach ($documents as $d) {
            DB::table('search_documents')->updateOrInsert(['type' => $d->type, 'ref' => $d->ref], [
                'title' => mb_substr($d->title, 0, 200), 'body' => $d->body, 'url' => $d->url, 'visibility' => $d->visibility,
                'hub_id' => $d->hubId, 'meta' => json_encode($d->meta), 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
    }

    public function delete(string $type, string $ref): void
    {
        DB::table('search_documents')->where('type', $type)->where('ref', $ref)->delete();
    }

    public function flush(string $type): void
    {
        DB::table('search_documents')->where('type', $type)->delete();
    }

    public function search(string $query, array $visibilities, int $limit = 30): array
    {
        $words = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [], static fn (string $w): bool => mb_strlen($w) >= 2));
        if ($words === []) {
            return [];
        }

        $rows = DB::table('search_documents')->whereIn('visibility', $visibilities)->limit(5000)->get(['type', 'ref', 'title', 'body', 'url']);
        $scored = [];

        foreach ($rows as $row) {
            $title = mb_strtolower((string) $row->title);
            $body = mb_strtolower((string) $row->body);
            $titleWords = preg_split('/[^\p{L}\p{N}]+/u', $title.' '.$body) ?: [];
            $score = 0;

            foreach ($words as $word) {
                if (str_contains($title, $word)) {
                    $score += 3;
                } elseif (str_contains($body, $word)) {
                    $score += 1;
                } elseif (mb_strlen($word) >= 4 && $this->closeMatch($word, $titleWords)) {
                    $score += 1;
                } else {
                    continue 2; // every word must match somewhere
                }
            }

            $scored[] = ['score' => $score, 'row' => ['type' => (string) $row->type, 'ref' => (string) $row->ref, 'title' => (string) $row->title, 'body' => (string) $row->body, 'url' => (string) $row->url]];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(static fn (array $s): array => $s['row'], array_slice($scored, 0, $limit));
    }

    /** @param list<string> $candidates */
    private function closeMatch(string $word, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (abs(mb_strlen($candidate) - mb_strlen($word)) <= 1 && levenshtein($word, $candidate) <= 1) {
                return true;
            }
        }

        return false;
    }
}
