<?php

declare(strict_types=1);

namespace Modules\Connect\Services;

use Modules\Connect\Models\Mentor;
use Modules\Core\Identity\Models\User;

/**
 * Explainable mentor suggestions: the mentor must help with the goal, have a free place and be approved;
 * then points for shared interests, language, place and format. Under-18s only see mentors cleared to
 * mentor them who meet at their hub (ADR-026).
 */
final readonly class Matching
{
    public function __construct(private Mentors $mentors) {}

    /**
     * @param  array{goal: string, interests?: string, languages?: list<string>, format?: string|null}  $wish
     * @return list<array{mentor: Mentor, score: int, reasons: list<array{key: string, params: array<string, string>}>}>
     */
    public function suggest(User $mentee, array $wish): array
    {
        $words = self::words((string) ($wish['interests'] ?? ''));
        $minor = $mentee->isMinor();

        return array_values(Mentor::query()->with('user')->where('status', 'approved')->where('user_id', '!=', $mentee->id)->get()
            ->filter(fn (Mentor $m): bool => in_array($wish['goal'], $m->goals, true) && $this->mentors->activeCount($m->user_id) < $m->capacity)
            ->filter(static fn (Mentor $m): bool => ! $minor || ($m->minors_ok && in_array('hub', $m->formats, true) && $m->hub_id !== null && $m->hub_id === $mentee->home_hub_id))
            ->map(static function (Mentor $m) use ($wish, $words, $mentee): array {
                $reasons = [['key' => 'goal', 'params' => ['goal' => $wish['goal']]]];
                $score = 40;
                $shared = array_values(array_intersect($words, self::words(implode(' ', $m->expertise).' '.$m->headline)));
                if ($shared !== []) {
                    $score += min(30, 10 * count($shared));
                    $reasons[] = ['key' => 'interests', 'params' => ['words' => implode(', ', array_slice($shared, 0, 3))]];
                }
                $languages = array_values(array_intersect($wish['languages'] ?? [], $m->languages));
                if ($languages !== []) {
                    $score += 10;
                    $reasons[] = ['key' => 'language', 'params' => ['languages' => implode(', ', $languages)]];
                }
                if (($m->hub_id !== null && $m->hub_id === $mentee->home_hub_id) || ($m->municipality_id !== null && $m->municipality_id === $mentee->municipality_id)) {
                    $score += 10;
                    $reasons[] = ['key' => 'near', 'params' => []];
                }
                if (($wish['format'] ?? null) !== null && in_array($wish['format'], $m->formats, true)) {
                    $score += 10;
                    $reasons[] = ['key' => 'format', 'params' => ['format' => (string) $wish['format']]];
                }

                return ['mentor' => $m, 'score' => min(100, $score), 'reasons' => $reasons];
            })
            ->sortByDesc('score')->take(10)->all());
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        $stop = ['and', 'the', 'for', 'with', 'from', 'that', 'this', 'want', 'like', 'work', 'help', 'need', 'about', 'into', 'have', 'more'];

        return array_values(array_unique(array_filter(preg_split('/[^a-z]+/', mb_strtolower($text)) ?: [], static fn (string $w): bool => mb_strlen($w) >= 4 && ! in_array($w, $stop, true))));
    }
}
