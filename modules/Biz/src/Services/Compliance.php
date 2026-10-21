<?php

declare(strict_types=1);

namespace Modules\Biz\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Biz\Events\ObligationDone;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;

/**
 * The compliance calendar (ADR-037): obligations worked out from the business's KasiStart facts and its confirmed
 * profile, due dates for the next 13 months, reminders, done with optional private proof. STARTER RULES - to be
 * confirmed by a tax adviser before launch; shown with a "not tax or legal advice" note.
 */
final readonly class Compliance
{
    /** rule => the KasiStart guide step it links to */
    public const RULES = ['cipc_annual' => 'cipc_register', 'turnover_interim' => 'sars_tax', 'turnover_annual' => 'sars_tax', 'vat' => 'sars_tax',
        'emp201' => 'uif_coida', 'coida' => 'uif_coida', 'bbbee' => 'bbbee_affidavit', 'trading_permit' => 'municipal_permit'];

    public const HORIZON_MONTHS = 13;

    public function __construct(private BusinessFacts $businesses, private DocumentVault $vault, private AuditLogger $audit) {}

    /** @return array{registered_on: string|null, turnover_tax: bool, vat_registered: bool, employs: bool, bbbee_on: string|null, permit_renewal_on: string|null, confirmed: bool} */
    public function profile(string $businessId): array
    {
        $p = DB::table('biz_compliance_profiles')->where('business_id', $businessId)->first();
        $facts = $this->businesses->facts($businessId);
        $done = $facts['steps_done'] ?? [];

        return [
            'registered_on' => $p->registered_on ?? null, 'turnover_tax' => (bool) ($p->turnover_tax ?? false), 'vat_registered' => (bool) ($p->vat_registered ?? false),
            // Pre-filled from KasiStart until the owner confirms: more than one person, or the UIF/COIDA step done.
            'employs' => $p !== null ? (bool) $p->employs : (($facts['people'] ?? 1) > 1 || in_array('uif_coida', $done, true)),
            'bbbee_on' => $p->bbbee_on ?? null, 'permit_renewal_on' => $p->permit_renewal_on ?? null, 'confirmed' => (bool) ($p->confirmed ?? false),
        ];
    }

    /** @param array<string, mixed> $data */
    public function saveProfile(string $businessId, User $by, array $data): void
    {
        $row = [
            'registered_on' => $data['registered_on'] ?? null, 'turnover_tax' => (bool) ($data['turnover_tax'] ?? false), 'vat_registered' => (bool) ($data['vat_registered'] ?? false),
            'employs' => (bool) ($data['employs'] ?? false), 'bbbee_on' => $data['bbbee_on'] ?? null, 'permit_renewal_on' => $data['permit_renewal_on'] ?? null,
            'confirmed' => true, 'updated_at' => now(), 'created_at' => now(),
        ];
        // Atomic insert-or-update (S31): two saves at once for the same business can't both insert.
        DB::table('biz_compliance_profiles')->upsert([['business_id' => $businessId, ...$row]], ['business_id'], array_values(array_diff(array_keys($row), ['created_at'])));
        $this->audit->record('biz.compliance_profile_saved', meta: ['business' => $businessId], actor: $by);
        $this->refresh($businessId);
    }

    /**
     * Due dates that apply, from today for the horizon.
     *
     * @return list<array{rule: string, due_on: string}>
     */
    public function schedule(string $businessId, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $until = $today->addMonths(self::HORIZON_MONTHS);
        $facts = $this->businesses->facts($businessId);
        if ($facts === null) {
            return [];
        }
        $p = $this->profile($businessId);
        $out = [];
        $add = static function (string $rule, CarbonImmutable $date) use (&$out, $today, $until): void {
            if ($date->gte($today->subMonths(2)) && $date->lte($until)) {
                $out[] = ['rule' => $rule, 'due_on' => $date->toDateString()];
            }
        };
        $years = [$today->year - 1, $today->year, $today->year + 1];

        // CIPC annual return: companies, co-ops and NPCs - within 30 business days of the registration anniversary.
        if (in_array($facts['legal_form'], ['pty', 'coop', 'npc'], true) && $p['registered_on'] !== null) {
            $reg = CarbonImmutable::parse($p['registered_on']);
            foreach ($years as $y) {
                $add('cipc_annual', self::addBusinessDays($reg->setYear($y), 30));
            }
        }
        // Turnover tax: interim payments end of August and end of February; annual return (placeholder date).
        if ($p['turnover_tax']) {
            foreach ($years as $y) {
                $add('turnover_interim', self::sarsDay(self::ymd($y, 8, 31)));
                $add('turnover_interim', self::sarsDay(self::ymd($y, 2, 1)->endOfMonth()->startOfDay()));
                $add('turnover_annual', self::sarsDay(self::ymd($y, 10, 31)));
            }
        }
        // VAT (category A, two-monthly): returns due on the 25th of Feb, Apr, Jun, Aug, Oct, Dec.
        if ($p['vat_registered']) {
            foreach ($years as $y) {
                foreach ([2, 4, 6, 8, 10, 12] as $m) {
                    $add('vat', self::sarsDay(self::ymd($y, $m, 25)));
                }
            }
        }
        // Employers: EMP201 by the 7th of every month; COIDA return of earnings by 31 May.
        if ($p['employs']) {
            for ($d = $today->subMonth()->startOfMonth(); $d->lte($until); $d = $d->addMonth()) {
                $add('emp201', self::sarsDay($d->setDay(7)));
            }
            foreach ($years as $y) {
                $add('coida', self::ymd($y, 5, 31));
            }
        }
        if ($p['bbbee_on'] !== null) {
            $last = CarbonImmutable::parse($p['bbbee_on']);
            foreach ($years as $y) {
                $add('bbbee', $last->setYear($y));
            }
        }
        if ($p['permit_renewal_on'] !== null) {
            $permit = CarbonImmutable::parse($p['permit_renewal_on']);
            foreach ($years as $y) {
                $add('trading_permit', $permit->setYear($y));
            }
        }

        return $out;
    }

    /** Make the stored calendar match the schedule: add new dates, drop open ones that no longer apply. */
    public function refresh(string $businessId): void
    {
        $wanted = $this->schedule($businessId);
        foreach ($wanted as $o) {
            DB::table('biz_obligations')->insertOrIgnore(['business_id' => $businessId, 'rule' => $o['rule'], 'due_on' => $o['due_on'], 'status' => 'due',
                'adjusted' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        $keys = array_map(static fn (array $o): string => $o['rule'].'|'.$o['due_on'], $wanted);
        foreach (DB::table('biz_obligations')->where('business_id', $businessId)->where('status', 'due')->where('adjusted', false)->get(['id', 'rule', 'due_on']) as $o) {
            if (! in_array($o->rule.'|'.CarbonImmutable::parse((string) $o->due_on)->toDateString(), $keys, true)) {
                DB::table('biz_obligations')->where('id', $o->id)->delete();
            }
        }
    }

    public function done(string $businessId, int $obligationId, User $by, ?UploadedFile $proof, ?User $subject = null): void
    {
        $o = DB::table('biz_obligations')->where('id', $obligationId)->where('business_id', $businessId)->first();
        if ($o === null || $o->status !== 'due') {
            throw new DomainException(__('biz.compliance.not_open'));
        }
        $documentId = $proof !== null ? $this->vault->store($subject ?? $by, $proof, 'other')->id : null;
        DB::table('biz_obligations')->where('id', $obligationId)->update(['status' => 'done', 'done_at' => now(), 'document_id' => $documentId, 'updated_at' => now()]);
        $this->audit->record('biz.obligation_done', meta: ['obligation' => $obligationId, 'rule' => $o->rule], actor: $by);
        event(new ObligationDone($subject ?? $by, $subject !== null ? $by->id : null, ['rule' => (string) $o->rule, 'on_time' => now()->toDateString() <= (string) $o->due_on]));
    }

    /** The owner moves a date (e.g. their accountant gave a different one). */
    public function moveDate(string $businessId, int $obligationId, User $by, string $date): void
    {
        $updated = DB::table('biz_obligations')->where('id', $obligationId)->where('business_id', $businessId)->where('status', 'due')
            ->update(['due_on' => CarbonImmutable::parse($date)->toDateString(), 'adjusted' => true, 'reminded_14' => null, 'reminded_3' => null, 'updated_at' => now()]);
        if ($updated === 0) {
            throw new DomainException(__('biz.compliance.not_open'));
        }
        $this->audit->record('biz.obligation_moved', meta: ['obligation' => $obligationId, 'date' => $date], actor: $by);
    }

    public static function upToDate(string $businessId): bool
    {
        return ! DB::table('biz_obligations')->where('business_id', $businessId)->where('status', 'due')->where('due_on', '<', now()->toDateString())->exists();
    }

    /** SARS: a due date on a weekend moves to the last business day before it (public holidays: to be added). */
    public static function sarsDay(CarbonImmutable $date): CarbonImmutable
    {
        while ($date->isWeekend()) {
            $date = $date->subDay();
        }

        return $date;
    }

    private static function ymd(int $year, int $month, int $day): CarbonImmutable
    {
        return CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    public static function addBusinessDays(CarbonImmutable $date, int $days): CarbonImmutable
    {
        while ($days > 0) {
            $date = $date->addDay();
            if (! $date->isWeekend()) {
                $days--;
            }
        }

        return $date;
    }
}
