<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Ai\AiService;
use Modules\Core\Identity\Models\User;
use Modules\Work\Models\WorkExperience;
use Modules\Work\Models\WorkProfile;

/**
 * AI suggestions for the CV. Suggestions are only proposals: the person accepts, edits or rejects
 * each one. Every suggestion is checked for facts (numbers, names, qualifications) that are not in
 * what the person wrote, so they can be warned before accepting.
 */
final readonly class CvAssistant
{
    /** Words that look like qualifications or achievements the AI must not invent. */
    private const RISKY = ['diploma', 'degree', 'certificate', 'certified', 'qualified', 'award', 'promoted', 'manager', 'supervisor', 'nqf', 'matric', 'licence', 'license'];

    public function __construct(private AiService $ai) {}

    /**
     * @return array{ok: bool, summary?: string, warnings?: list<string>, reason?: string|null}
     */
    public function suggestSummary(User $user, WorkProfile $profile, ?User $by = null): array
    {
        $about = trim(($profile->headline ?? '')."\n".($profile->summary ?? ''));
        $experience = WorkExperience::query()->where('user_id', $user->id)->orderBy('position')->pluck('title')->implode(', ');
        $skills = DB::table('work_skills')->where('user_id', $user->id)->pluck('name')->implode(', ');

        $result = $this->ai->run('work.cv_summary', ['about' => $about, 'experience' => $experience ?: 'none yet', 'skills' => $skills ?: 'none listed'], $user, $by);
        if (! $result->ok) {
            return ['ok' => false, 'reason' => $result->reason];
        }

        $summary = trim((string) $result->data['summary']);

        return ['ok' => true, 'summary' => $summary, 'warnings' => $this->unsupported($about.' '.$experience.' '.$skills, $summary)];
    }

    /**
     * @return array{ok: bool, bullets?: list<string>, warnings?: list<string>, reason?: string|null}
     */
    public function suggestBullets(WorkExperience $experience, User $user, ?User $by = null): array
    {
        $description = trim((string) $experience->description);
        if (mb_strlen($description) < 10) {
            return ['ok' => false, 'reason' => 'too_short'];
        }

        $result = $this->ai->run('work.cv_experience', [
            'kind' => str_replace('_', ' ', $experience->kind), 'title' => $experience->title,
            'current' => $experience->ended === null && $experience->duration === null ? 'yes' : 'no', 'description' => $description,
        ], $user, $by);

        $bullets = $result->ok ? array_values(array_filter(array_map(static fn ($b): string => trim((string) $b), (array) $result->data['bullets']))) : [];
        if ($bullets === []) {
            return ['ok' => false, 'reason' => $result->reason ?? 'invalid'];
        }

        $bullets = array_slice($bullets, 0, 4);
        $experience->forceFill(['suggested_bullets' => $bullets])->save();

        return ['ok' => true, 'bullets' => $bullets, 'warnings' => $this->unsupported($experience->title.' '.$experience->organisation.' '.$description, implode('. ', $bullets))];
    }

    /**
     * Facts in the suggestion that the person never wrote: numbers, capitalised names and
     * qualification-like words. Shown as "please check" warnings.
     *
     * @return list<string>
     */
    public function unsupported(string $source, string $suggestion): array
    {
        $haystack = mb_strtolower($source);
        $warnings = [];

        preg_match_all('/\b\d[\d.,%]*\b/u', $suggestion, $numbers);
        foreach ($numbers[0] as $number) {
            if (preg_match('/(?<![\d.,])'.preg_quote(mb_strtolower($number), '/').'(?![\d])/u', $haystack) !== 1) {
                $warnings[] = $number;
            }
        }

        // Capitalised words not at the start of a sentence (likely names of employers, places, products).
        preg_match_all('/(?<![.!?]\s)(?<!^)\b([A-Z][a-z]{2,})\b/u', $suggestion, $names);
        foreach ($names[1] as $name) {
            if (! str_contains($haystack, mb_strtolower($name)) && ! in_array(mb_strtolower($name), ['i', 'south', 'african', 'english'], true)) {
                $warnings[] = $name;
            }
        }

        foreach (self::RISKY as $word) {
            if (preg_match('/\b'.$word.'\b/i', $suggestion) === 1 && ! str_contains($haystack, $word)) {
                $warnings[] = $word;
            }
        }

        return array_values(array_unique($warnings));
    }
}
