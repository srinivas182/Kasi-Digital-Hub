<?php

declare(strict_types=1);

namespace Modules\Brand\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Brand\Events\PostPosted;
use Modules\Brand\Notifications\BrandNotification;
use Modules\Core\Ai\AiService;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\Notifier;

/**
 * The content calendar (ADR-039): planned posts with reminders and "mark as posted"; "Plan my month" builds dated
 * slots from the South African calendar in code and asks the AI only for the wording (with plain fallbacks), so the
 * plan always works. No automatic posting.
 */
final readonly class Calendar
{
    public const CHANNELS = ['whatsapp', 'facebook', 'instagram', 'poster'];

    public const MAX_IDEAS = 12;

    public function __construct(private BusinessFacts $businesses) {}

    /**
     * South African moments in a month: public holidays (Sunday -> Monday), payday, grant days, and seasonal days.
     *
     * @return list<array{date: string, key: string, template: string}>
     */
    public static function moments(int $year, int $month): array
    {
        $easter = self::easter($year);
        $fixed = ['01-01' => 'new_year', '03-21' => 'human_rights', '04-27' => 'freedom', '05-01' => 'workers', '06-16' => 'youth', '08-09' => 'womens',
            '09-24' => 'heritage', '12-16' => 'reconciliation', '12-25' => 'christmas', '12-26' => 'goodwill'];
        $days = [];
        foreach ($fixed as $md => $key) {
            $d = CarbonImmutable::parse("{$year}-{$md}");
            $days[] = [$d->isSunday() ? $d->addDay() : $d, $key, 'square'];
        }
        $days[] = [$easter->subDays(2), 'good_friday', 'now_open'];
        $days[] = [$easter->addDay(), 'family_day', 'square'];
        $first = CarbonImmutable::create($year, $month, 1) ?? CarbonImmutable::now()->startOfMonth();
        $days[] = [$first->addDays(2), 'grant_days', 'offer']; // grant payments early in the month
        $days[] = [$first->setDay(25), 'payday', 'offer'];
        $last = $first->endOfMonth()->startOfDay();
        while ($last->isWeekend()) {
            $last = $last->subDay();
        }
        $days[] = [$last, 'month_end', 'price_list'];
        $days[] = [CarbonImmutable::create($year, 2, 14) ?? $first, 'valentines', 'offer'];
        $days[] = [CarbonImmutable::parse("second sunday of may {$year}"), 'mothers_day', 'offer'];
        $days[] = [CarbonImmutable::parse("third sunday of june {$year}"), 'fathers_day', 'offer'];
        $days[] = [CarbonImmutable::parse("fourth thursday of november {$year}")->addDay(), 'black_friday', 'offer'];
        $days[] = [CarbonImmutable::create($year, 1, 12) ?? $first, 'back_to_school', 'price_list'];
        $days[] = [CarbonImmutable::create($year, 12, 1) ?? $first, 'festive_season', 'poster'];
        $out = [];
        foreach ($days as [$date, $key, $template]) {
            if ($date->year === $year && $date->month === $month) {
                $out[] = ['date' => $date->toDateString(), 'key' => $key, 'template' => $template];
            }
        }
        usort($out, static fn (array $a, array $b): int => $a['date'] <=> $b['date']); // stable: holidays were added first

        // One moment per day; a public holiday wins over payday or month-end on the same day.
        $seen = [];

        return array_values(array_filter($out, static function (array $m) use (&$seen): bool {
            if (isset($seen[$m['date']])) {
                return false;
            }
            $seen[$m['date']] = true;

            return true;
        }));
    }

    /** Easter Sunday (anonymous Gregorian algorithm). */
    public static function easter(int $y): CarbonImmutable
    {
        $a = $y % 19;
        $b = intdiv($y, 100);
        $c = $y % 100;
        $h = (19 * $a + $b - intdiv($b, 4) - intdiv(8 * $b + 13, 25) + 15) % 30;
        $l = (32 + 2 * ($b % 4) + 2 * intdiv($c, 4) - $h - $c % 4) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $y, $month, $day));
    }

    /**
     * Plan a month: moments plus a weekly post, 8 to 12 slots, worded by the AI (or plain fallbacks), saved as suggestions.
     *
     * @return int the number of ideas added
     */
    public function planMonth(string $businessId, User $by, int $year, int $month, ?User $subject = null): int
    {
        $start = CarbonImmutable::create($year, $month, 1) ?? CarbonImmutable::now()->startOfMonth();
        $slots = self::moments($year, $month);
        $taken = array_column($slots, 'date');
        for ($d = $start->next(CarbonImmutable::MONDAY); $d->month === $month && count($slots) < self::MAX_IDEAS; $d = $d->addWeek()) {
            if (! in_array($d->toDateString(), $taken, true)) {
                $slots[] = ['date' => $d->toDateString(), 'key' => 'new_week', 'template' => 'status'];
            }
        }
        usort($slots, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);
        $slots = array_slice(array_values(array_filter($slots, static fn (array $s): bool => $s['date'] >= now()->toDateString())), 0, self::MAX_IDEAS);
        if ($slots === []) {
            return 0;
        }
        $facts = $this->businesses->facts($businessId);
        $name = (string) ($facts['name'] ?? '');
        $result = app(AiService::class)->run('brand.month_plan', ['name' => $name, 'sells' => (string) ($facts['sector'] ?? ''),
            'moments' => implode("\n", array_map(static fn (array $s, int $i): string => ($i + 1).'. '.$s['date'].' - '.__('brand.moment.'.$s['key']), $slots, array_keys($slots)))],
            $subject ?? $by, $by, $facts['hub_id'] ?? null);
        $ideas = $result->ok ? array_values((array) ($result->data['ideas'] ?? [])) : [];
        foreach ($slots as $i => $slot) {
            $text = mb_substr(trim((string) ($ideas[$i] ?? '')), 0, 500);
            DB::table('brand_posts')->insert(['business_id' => $businessId, 'channel' => 'whatsapp', 'planned_at' => $slot['date'].' 08:00:00',
                'text' => $text !== '' && ! str_starts_with($text, '(Demo AI)') ? $text : __('brand.moment_post.'.$slot['key'], ['business' => $name]),
                'template' => $slot['template'], 'status' => 'planned', 'suggested' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        return count($slots);
    }

    /** @param array{channel: string, planned_at: string, text: string, design_id?: int|null} $data */
    public function save(string $businessId, array $data, ?int $postId = null): int
    {
        if (! in_array($data['channel'], self::CHANNELS, true)) {
            throw new DomainException(__('brand.calendar.invalid'));
        }
        $designId = isset($data['design_id']) && DB::table('brand_designs')->where('id', $data['design_id'])->where('business_id', $businessId)->exists() ? (int) $data['design_id'] : null;
        $values = ['channel' => $data['channel'], 'planned_at' => CarbonImmutable::parse($data['planned_at'])->toDateTimeString(), 'text' => mb_substr(trim($data['text']), 0, 500),
            'design_id' => $designId, 'reminded_at' => null, 'updated_at' => now()];
        if ($postId !== null) {
            if (DB::table('brand_posts')->where('id', $postId)->where('business_id', $businessId)->update($values) === 0) {
                throw new DomainException(__('brand.calendar.not_found'));
            }

            return $postId;
        }

        return (int) DB::table('brand_posts')->insertGetId([...$values, 'business_id' => $businessId, 'status' => 'planned', 'suggested' => false, 'created_at' => now()]);
    }

    public function mark(string $businessId, int $postId, string $status): void
    {
        if (! in_array($status, ['posted', 'skipped', 'planned'], true)) {
            throw new DomainException(__('brand.calendar.invalid'));
        }
        DB::table('brand_posts')->where('id', $postId)->where('business_id', $businessId)
            ->update(['status' => $status, 'posted_at' => $status === 'posted' ? now() : null, 'updated_at' => now()]);
    }

    public function posted(string $businessId, int $postId, User $by, ?User $subject = null): void
    {
        $this->mark($businessId, $postId, 'posted');
        event(new PostPosted($subject ?? $by, $subject !== null ? $by->id : null, ['business' => $businessId]));
    }

    public function delete(string $businessId, int $postId): void
    {
        DB::table('brand_posts')->where('id', $postId)->where('business_id', $businessId)->delete();
    }

    /** Remind owners of posts that are due (once each). */
    public function sendReminders(Notifier $notifier): int
    {
        $sent = 0;
        foreach (DB::table('brand_posts')->where('status', 'planned')->whereNull('reminded_at')->where('planned_at', '<=', now())
            ->where('planned_at', '>=', now()->subDay())->limit(500)->get() as $post) {
            DB::table('brand_posts')->where('id', $post->id)->update(['reminded_at' => now()]);
            foreach ($this->businesses->facts((string) $post->business_id)['owner_ids'] ?? [] as $ownerId) {
                $owner = User::query()->find($ownerId);
                if ($owner instanceof User) {
                    $notifier->send($owner, new BrandNotification('post_due', ['channel' => __('brand.channel.'.$post->channel), 'text' => mb_substr((string) $post->text, 0, 80)], '/brand/calendar'));
                    $sent++;
                }
            }
        }

        return $sent;
    }
}
