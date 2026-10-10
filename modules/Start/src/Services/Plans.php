<?php

declare(strict_types=1);

namespace Modules\Start\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Ai\AiService;
use Modules\Core\Identity\Models\User;
use Modules\Start\Events\PlanCompleted;
use Modules\Start\Models\Business;

/**
 * The business plan builder: guided sections, AI help that never adds numbers, and two calculators.
 */
final readonly class Plans
{
    public const SECTIONS = ['problem', 'offer', 'pricing', 'marketing', 'money', 'next_steps'];

    public function __construct(private AiService $ai, private Businesses $businesses) {}

    /** @return array{sections: array<string, string>, numbers: array<string, float>, completed: bool} */
    public function for(Business $business): array
    {
        $row = DB::table('start_plans')->where('business_id', $business->id)->first();

        return [
            'sections' => array_merge(array_fill_keys(self::SECTIONS, ''), (array) json_decode((string) ($row->sections ?? '{}'), true)),
            'numbers' => (array) json_decode((string) ($row->numbers ?? '{}'), true),
            'completed' => $row?->completed_at !== null,
        ];
    }

    /**
     * @param  array<string, string>  $sections
     * @param  array<string, float|int|null>  $numbers
     */
    public function save(Business $business, array $sections, array $numbers, User $by): void
    {
        $clean = array_intersect_key(array_map(static fn ($v): string => mb_substr(trim((string) $v), 0, 3000), $sections), array_flip(self::SECTIONS));
        $complete = count(array_filter($clean, static fn (string $v): bool => mb_strlen($v) >= 20)) === count(self::SECTIONS);
        $existing = DB::table('start_plans')->where('business_id', $business->id)->first();

        DB::table('start_plans')->updateOrInsert(['business_id' => $business->id], [
            'sections' => json_encode($clean), 'numbers' => json_encode(array_filter($numbers, static fn ($v): bool => $v !== null)),
            'completed_at' => $complete ? ($existing->completed_at ?? now()) : null, 'updated_at' => now(), 'created_at' => $existing->created_at ?? now(),
        ]);
        if ($complete && ($existing === null || $existing->completed_at === null)) {
            event(new PlanCompleted($by, null, ['business' => $business->id]));
        }
        $this->businesses->refresh($business);
    }

    /**
     * Price from cost and markup, and how many items cover the fixed costs each month.
     *
     * @return array{price: float|null, profit_per_item: float|null, break_even_items: int|null, warning: string|null}
     */
    public static function calculate(?float $costPerItem, ?float $markupPercent, ?float $price, ?float $fixedCostsPerMonth): array
    {
        $price ??= ($costPerItem !== null && $markupPercent !== null) ? round($costPerItem * (1 + $markupPercent / 100), 2) : null;
        $profit = ($price !== null && $costPerItem !== null) ? round($price - $costPerItem, 2) : null;
        $warning = $profit !== null && $profit <= 0 ? 'start.calc.loss' : null;
        $breakEven = ($profit !== null && $profit > 0 && $fixedCostsPerMonth !== null) ? (int) ceil($fixedCostsPerMonth / $profit) : null;

        return ['price' => $price, 'profit_per_item' => $profit, 'break_even_items' => $breakEven, 'warning' => $warning];
    }

    /** @return array{ok: bool, text?: string, warnings?: list<string>, reason?: string|null} */
    public function improve(Business $business, string $section, string $text, User $by): array
    {
        $result = $this->ai->run('start.plan_section', ['business' => $business->name, 'sells' => (string) $business->sells, 'section' => $section, 'text' => $text], by: $by);
        $improved = $result->ok ? trim((string) ($result->data['text'] ?? '')) : '';
        if ($improved === '') {
            return ['ok' => false, 'reason' => $result->reason ?? 'invalid'];
        }

        // Numbers the person never wrote are flagged, like the CV writer.
        preg_match_all('/\d[\d\s.,]*/u', $improved, $numbers);
        $warnings = array_values(array_unique(array_filter(array_map('trim', $numbers[0]), static fn (string $n): bool => $n !== '' && ! str_contains($text, $n))));

        return ['ok' => true, 'text' => $improved, 'warnings' => $warnings];
    }
}
