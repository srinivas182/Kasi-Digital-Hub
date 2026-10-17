<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Support\Format\SaFormat;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Identity\Contracts\SmsSender;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * Support (ADR-029): help requests and safe account help. No impersonation: staff see counts and statuses,
 * never content; sensitive changes need a one-time code the person reads back (or a hub ID check).
 */
final readonly class Support
{
    public const CATEGORIES = ['account', 'work', 'learn', 'start', 'mentoring', 'hub', 'payments', 'safety', 'other'];

    public const CODE_MINUTES = 15;

    public function __construct(private AuditLogger $audit, private Notifier $notifier, private DocumentVault $vault, private SmsSender $sms) {}

    public function open(User $user, string $category, string $subject, string $body, ?UploadedFile $screenshot): string
    {
        $id = (string) Str::ulid();
        $number = 'HELP-'.str_pad((string) (DB::table('support_tickets')->count() + 1), 6, '0', STR_PAD_LEFT);
        DB::table('support_tickets')->insert(['id' => $id, 'number' => $number, 'user_id' => $user->id, 'category' => $category, 'subject' => mb_substr($subject, 0, 140),
            'status' => $category === 'safety' ? 'escalated' : 'open', 'created_at' => now(), 'updated_at' => now()]);
        $this->message($id, $user, false, $body, false, $screenshot);
        if ($category === 'safety') {
            // Safety worries go straight to the safeguarding officers (S18) as well.
            User::query()->whereIn('id', RoleAssignment::query()->where('role', 'safeguarding_officer')->pluck('user_id'))->get()
                ->each(fn (User $o) => $this->notifier->send($o, new SupportNotification('safety', ['number' => $number], '/admin/support/'.$id)));
        }

        return $id;
    }

    public function message(string $ticketId, User $sender, bool $fromStaff, string $body, bool $internal = false, ?UploadedFile $screenshot = null): void
    {
        $ticket = DB::table('support_tickets')->where('id', $ticketId)->first();
        if ($ticket === null) {
            throw new DomainException('No such request');
        }
        $document = $screenshot !== null ? $this->vault->store($sender, $screenshot, 'other') : null;
        DB::table('support_messages')->insert(['id' => (string) Str::ulid(), 'ticket_id' => $ticketId, 'sender_id' => $sender->id, 'from_staff' => $fromStaff, 'internal' => $internal,
            'body' => mb_substr(trim($body), 0, 4000), 'document_id' => $document?->id, 'created_at' => now()]);
        $update = ['updated_at' => now()];
        if ($fromStaff && ! $internal) {
            $update += ['status' => 'waiting', 'first_response_at' => $ticket->first_response_at ?? now()];
            if (($person = User::query()->find($ticket->user_id)) instanceof User) {
                $this->notifier->send($person, new SupportNotification('reply', ['number' => (string) $ticket->number], '/help/requests/'.$ticketId));
            }
        } elseif (! $fromStaff && $ticket->status === 'waiting') {
            $update['status'] = 'open';
        }
        DB::table('support_tickets')->where('id', $ticketId)->update($update);
    }

    /** Working days (Mon-Fri) since a time - for the 2-working-day first-response target. */
    public static function workingDaysSince(\DateTimeInterface $from): int
    {
        $days = 0;
        $d = CarbonImmutable::instance($from)->startOfDay()->addDay();
        $today = now()->startOfDay();
        while ($d->lte($today)) {
            $days += $d->isWeekday() ? 1 : 0;
            $d = $d->addDay();
        }

        return $days;
    }

    /**
     * Counts and statuses only - never content (ADR-029). Each look-up is audited with a reason.
     *
     * @return array<string, mixed>
     */
    public function summary(User $person, User $staff, string $reason): array
    {
        $this->audit->record('support.summary_viewed', $person, meta: ['reason' => $reason], actor: $staff);
        $count = static fn (string $table, string $column = 'user_id', array $where = []): int => Schema::hasTable($table)
            ? DB::table($table)->where($column, $person->id)->where($where)->count() : 0;

        return [
            'name' => $person->fullName(), 'phone' => SaFormat::maskedPhone($person->phone), 'status' => $person->status,
            'locked' => $person->isPinLocked(), 'minor' => $person->isMinor(), 'joined' => $person->created_at?->toDateString(),
            'lastSeen' => DB::table('user_devices')->where('user_id', $person->id)->max('last_seen_at'),
            'roles' => RoleAssignment::query()->where('user_id', $person->id)->pluck('role')->unique()->values(),
            'counts' => [
                'documents_waiting' => $count('documents', 'user_id', ['status' => 'uploaded']),
                'applications' => $count('work_applications'),
                'enrolments' => $count('learn_enrolments'),
                'businesses' => Schema::hasTable('start_business_members') ? DB::table('start_business_members')->where('user_id', $person->id)->count() : 0,
                'mentorships' => Schema::hasTable('connect_mentorships') ? DB::table('connect_mentorships')->where('mentee_id', $person->id)->count() : 0,
                'tickets' => $count('support_tickets'),
            ],
            'notifications' => DB::table('notification_deliveries')->where('user_id', $person->id)->orderByDesc('created_at')->limit(10)
                ->get(['id', 'notification', 'channel', 'status', 'created_at'])->map(static fn (object $d): array => (array) $d)->all(),
        ];
    }

    /** Send a one-time code to the person (or to a new number) for a sensitive change. */
    public function sendCode(User $person, string $action, User $staff, ?string $toPhone = null): void
    {
        $code = (string) random_int(100000, 999999);
        DB::table('support_codes')->insert(['user_id' => $person->id, 'requested_by' => $staff->id, 'action' => $action, 'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_MINUTES), 'created_at' => now()]);
        $this->sms->send($toPhone ?? $person->phone, __('admin.support.code_sms', ['code' => $code, 'minutes' => self::CODE_MINUTES]));
        $this->audit->record('support.code_sent', $person, meta: ['action' => $action], actor: $staff);
    }

    public function checkCode(User $person, string $action, string $code): bool
    {
        $row = DB::table('support_codes')->where('user_id', $person->id)->where('action', $action)->whereNull('used_at')->where('expires_at', '>', now())->orderByDesc('id')->first();
        if ($row === null || $row->attempts >= 5) {
            return false;
        }
        if (! Hash::check($code, (string) $row->code_hash)) {
            DB::table('support_codes')->where('id', $row->id)->increment('attempts');

            return false;
        }
        DB::table('support_codes')->where('id', $row->id)->update(['used_at' => now()]);

        return true;
    }

    public function unlock(User $person, User $staff, string $code): void
    {
        if (! $this->checkCode($person, 'unlock', $code)) {
            throw new DomainException(__('admin.support.bad_code'));
        }
        $person->forceFill(['pin_failed_attempts' => 0, 'pin_locked_until' => null])->save();
        $this->audit->record('support.unlocked', $person, actor: $staff);
        $this->notifier->send($person, new SupportNotification('unlocked', [], '/login'));
    }

    /** A new phone number: the person proved ID at a hub (recorded) and read back the code sent to the new number. */
    public function changePhone(User $person, User $staff, string $newPhone, string $code, string $hubCheckNote): void
    {
        if (User::query()->where('phone', $newPhone)->exists()) {
            throw new DomainException(__('admin.support.phone_taken'));
        }
        if (! $this->checkCode($person, 'phone_change', $code)) {
            throw new DomainException(__('admin.support.bad_code'));
        }
        $old = $person->phone;
        $person->forceFill(['phone' => $newPhone])->save();
        $this->audit->record('support.phone_changed', $person, meta: ['hub_check' => $hubCheckNote, 'old' => SaFormat::maskedPhone($old)], actor: $staff);
        $this->sms->send($old, __('admin.support.phone_changed_sms'));
    }
}
