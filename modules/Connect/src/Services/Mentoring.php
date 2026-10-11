<?php

declare(strict_types=1);

namespace Modules\Connect\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Connect\Events\MentorshipEnded;
use Modules\Connect\Events\MentorshipStarted;
use Modules\Connect\Events\SessionHeld;
use Modules\Connect\Models\Mentor;
use Modules\Connect\Models\Mentorship;
use Modules\Connect\Notifications\ConnectNotification;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Hubs\HubSessions;
use Modules\Core\Identity\Contracts\SmsSender;
use Modules\Core\Identity\Models\GuardianConsent;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;

/**
 * Requests, agreements, sessions, messages and endings (ADR-026). Under-18s: hub sessions only, no
 * messages, guardian informed. Contact details can't be shared in messages before two sessions.
 */
final readonly class Mentoring
{
    public const MAX_PENDING = 3;

    public const CONTACT_FREE_SESSIONS = 2;

    private const CONTACT = '/(\+?27|0)[\s-]?[6-8]\d([\s-]?\d){7}|[\w.+-]+@[\w-]+\.[\w.]+|\b(whats\s?app|wa\.me|telegram|instagram|facebook|tiktok|snap(chat)?)\b|(^|\s)@[a-z0-9_.]{3,}/i';

    public function __construct(private Mentors $mentors, private Notifier $notifier, private AuditLogger $audit, private ModerationService $moderation) {}

    public function request(User $mentee, Mentor $mentor, string $goal, ?string $message): string
    {
        if ($mentor->status !== 'approved' || $this->mentors->activeCount($mentor->user_id) >= $mentor->capacity || ! in_array($goal, $mentor->goals, true)) {
            throw new DomainException(__('connect.request.unavailable'));
        }
        if ($mentee->isMinor() && (! $mentor->minors_ok || $mentor->hub_id === null || $mentor->hub_id !== $mentee->home_hub_id)) {
            throw new DomainException(__('connect.request.unavailable'));
        }
        if (DB::table('connect_requests')->where('mentee_id', $mentee->id)->where('status', 'pending')->count() >= self::MAX_PENDING) {
            throw new DomainException(__('connect.request.too_many', ['count' => self::MAX_PENDING]));
        }
        if (Mentorship::query()->where('mentee_id', $mentee->id)->where('mentor_id', $mentor->user_id)->whereIn('status', ['proposed', 'active', 'paused'])->exists()) {
            throw new DomainException(__('connect.request.already'));
        }
        $id = (string) Str::ulid();
        DB::table('connect_requests')->insert(['id' => $id, 'mentee_id' => $mentee->id, 'mentor_id' => $mentor->user_id, 'goal' => $goal,
            'message' => $message !== null ? mb_substr(trim($message), 0, 1000) : null, 'created_at' => now(), 'updated_at' => now()]);
        $this->notifier->send($mentor->user, new ConnectNotification('request', ['name' => $mentee->first_name], '/connect/mentor'));

        return $id;
    }

    public function answer(string $requestId, User $mentor, bool $accept): ?Mentorship
    {
        $request = DB::table('connect_requests')->where('id', $requestId)->where('mentor_id', $mentor->id)->where('status', 'pending')->first();
        if ($request === null) {
            throw new DomainException(__('connect.request.gone'));
        }
        DB::table('connect_requests')->where('id', $requestId)->update(['status' => $accept ? 'accepted' : 'declined', 'updated_at' => now()]);
        $mentee = User::query()->whereKey($request->mentee_id)->firstOrFail();
        if (! $accept) {
            $this->notifier->send($mentee, new ConnectNotification('declined', ['name' => $mentor->first_name], '/connect/find'));

            return null;
        }
        $mentorship = Mentorship::query()->create(['mentor_id' => $mentor->id, 'mentee_id' => $mentee->id, 'goal' => $request->goal, 'goals' => [],
            'format' => $mentee->isMinor() ? 'hub' : 'phone', 'status' => 'proposed']); // status set explicitly: DB defaults aren't on new models
        $this->notifier->send($mentee, new ConnectNotification('accepted', ['name' => $mentor->first_name], '/connect/mentorships/'.$mentorship->id));

        return $mentorship;
    }

    /** @param array{goals: list<string>, per_month: int, months: int, format: string} $terms */
    public function propose(Mentorship $m, User $by, array $terms): void
    {
        if ($m->status !== 'proposed') {
            throw new DomainException(__('connect.agreement.locked'));
        }
        if (User::query()->findOrFail($m->mentee_id)->isMinor() && $terms['format'] !== 'hub') {
            throw new DomainException(__('connect.minor.hub_only'));
        }
        $m->forceFill([...$terms, 'goals' => array_slice(array_values(array_filter(array_map('trim', $terms['goals']))), 0, 3),
            'mentor_agreed_at' => $by->id === $m->mentor_id ? now() : null, 'mentee_agreed_at' => $by->id === $m->mentee_id ? now() : null])->save();
        $other = User::query()->findOrFail($by->id === $m->mentor_id ? $m->mentee_id : $m->mentor_id);
        $this->notifier->send($other, new ConnectNotification('agreement', ['name' => $by->first_name], '/connect/mentorships/'.$m->id));
    }

    public function agree(Mentorship $m, User $by): void
    {
        if ($m->status !== 'proposed' || $m->goals === []) {
            throw new DomainException(__('connect.agreement.not_ready'));
        }
        $m->forceFill($by->id === $m->mentor_id ? ['mentor_agreed_at' => now()] : ['mentee_agreed_at' => now()])->save();
        if ($m->mentor_agreed_at !== null && $m->mentee_agreed_at !== null) {
            $m->forceFill(['status' => 'active', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonths($m->months)->toDateString()])->save();
            $mentee = User::query()->findOrFail($m->mentee_id);
            event(new MentorshipStarted($mentee, $m->mentor_id, ['mentorship' => $m->id, 'goal' => $m->goal, 'minor' => $mentee->isMinor()]));
            if ($mentee->isMinor()) {
                $this->informGuardian($mentee, User::query()->findOrFail($m->mentor_id));
            }
        }
    }

    public function schedule(Mentorship $m, User $by, CarbonImmutable $startsAt, int $minutes, string $format, ?string $link, ?string $hubId, ?string $room): int
    {
        if ($m->status !== 'active') {
            throw new DomainException(__('connect.session.not_active'));
        }
        $mentee = User::query()->findOrFail($m->mentee_id);
        if ($mentee->isMinor() && $format !== 'hub') {
            throw new DomainException(__('connect.minor.hub_only'));
        }
        $eventId = null;
        if ($format === 'hub') {
            $hub = $hubId ?? Mentor::query()->whereKey($m->mentor_id)->value('hub_id');
            if ($hub === null || ! app()->bound(HubSessions::class)) {
                throw new DomainException(__('connect.session.hub_needed'));
            }
            $sessions = app(HubSessions::class);
            $eventId = $sessions->schedule((string) $hub, __('connect.session.hub_title'), null, $startsAt, $startsAt->addMinutes($minutes), 2, $room, $by);
            $sessions->register($eventId, $mentee);
            $sessions->register($eventId, User::query()->findOrFail($m->mentor_id));
        }
        $id = (int) DB::table('connect_sessions')->insertGetId(['mentorship_id' => $m->id, 'starts_at' => $startsAt->utc(), 'minutes' => $minutes, 'format' => $format,
            'link' => $format === 'video' ? $link : null, 'event_id' => $eventId, 'created_at' => now(), 'updated_at' => now()]);
        $other = User::query()->findOrFail($by->id === $m->mentor_id ? $m->mentee_id : $m->mentor_id);
        $this->notifier->send($other, new ConnectNotification('session', ['name' => $by->first_name], '/connect/mentorships/'.$m->id));

        return $id;
    }

    /**
     * The mentor records a held session: shared notes and actions (both see), private notes (mentor only).
     *
     * @param  list<string>  $actions
     */
    public function record(int $sessionId, User $mentor, string $sharedNotes, array $actions, ?string $privateNotes): void
    {
        $s = DB::table('connect_sessions')->where('id', $sessionId)->first();
        $m = $s !== null ? Mentorship::query()->find($s->mentorship_id) : null;
        if ($s === null || ! $m instanceof Mentorship || $m->mentor_id !== $mentor->id || $s->status !== 'scheduled') {
            throw new DomainException(__('connect.session.not_open'));
        }
        DB::table('connect_sessions')->where('id', $sessionId)->update(['status' => 'held', 'shared_notes' => mb_substr($sharedNotes, 0, 4000),
            'actions' => json_encode(array_values(array_filter(array_map('trim', $actions)))), 'private_notes' => $privateNotes, 'updated_at' => now()]);
        DB::table('connect_mentors')->where('user_id', $mentor->id)->increment('minutes_mentored', (int) $s->minutes);
        $mentee = User::query()->findOrFail($m->mentee_id);
        $this->notifier->send($mentee, new ConnectNotification('helpful', ['name' => $mentor->first_name], '/connect/mentorships/'.$m->id));
        event(new SessionHeld($mentee, $mentor->id, ['mentorship' => $m->id, 'minutes' => (int) $s->minutes, 'format' => (string) $s->format]));
    }

    public function helpful(int $sessionId, User $mentee, bool $helpful): void
    {
        $s = DB::table('connect_sessions')->where('id', $sessionId)->first();
        $m = $s !== null ? Mentorship::query()->find($s->mentorship_id) : null;
        if (! $m instanceof Mentorship || $m->mentee_id !== $mentee->id || $s->status !== 'held') {
            throw new DomainException(__('connect.session.not_open'));
        }
        DB::table('connect_sessions')->where('id', $sessionId)->update(['helpful' => $helpful]);
    }

    /** @return array{sent: bool, reason: string|null} */
    public function message(Mentorship $m, User $sender, string $body): array
    {
        if (! in_array($m->status, ['proposed', 'active'], true) || ! $m->involves($sender->id)) {
            throw new DomainException(__('connect.messages.closed'));
        }
        if (User::query()->findOrFail($m->mentee_id)->isMinor()) {
            throw new DomainException(__('connect.minor.no_messages'));
        }
        $contact = preg_match(self::CONTACT, $body) === 1;
        $held = DB::table('connect_sessions')->where('mentorship_id', $m->id)->where('status', 'held')->count();
        if ($contact && $held < self::CONTACT_FREE_SESSIONS) {
            return ['sent' => false, 'reason' => 'contact'];
        }
        $id = (string) Str::ulid();
        $flag = $this->moderation->check($body, 'connect_message', $id, $sender, useAi: false)['verdict'] === 'flag';
        DB::table('connect_messages')->insert(['id' => $id, 'mentorship_id' => $m->id, 'sender_id' => $sender->id, 'body' => mb_substr(trim($body), 0, 2000), 'held' => $flag, 'created_at' => now()]);
        if (! $flag) {
            $other = User::query()->findOrFail($sender->id === $m->mentor_id ? $m->mentee_id : $m->mentor_id);
            $this->notifier->send($other, new ConnectNotification('message', ['name' => $sender->first_name], '/connect/mentorships/'.$m->id));
        }

        return ['sent' => ! $flag, 'reason' => $flag ? 'held' : ($contact ? 'contact_reminder' : null)];
    }

    /** @param list<bool> $goalsMet */
    public function end(Mentorship $m, User $by, string $reason, array $goalsMet = [], ?string $feedback = null): void
    {
        if ($m->status === 'ended') {
            return;
        }
        $mine = $by->id === $m->mentor_id ? 'mentor_feedback' : 'mentee_feedback';
        $m->forceFill(['status' => 'ended', 'ended_reason' => mb_substr($reason, 0, 300), 'ended_by' => $by->id, 'goals_met' => $goalsMet === [] ? null : $goalsMet, $mine => $feedback])->save();
        event(new MentorshipEnded(User::query()->findOrFail($m->mentee_id), $by->id, ['mentorship' => $m->id, 'goals' => count($m->goals), 'met' => count(array_filter($goalsMet))]));
        $other = User::query()->findOrFail($by->id === $m->mentor_id ? $m->mentee_id : $m->mentor_id);
        $this->notifier->send($other, new ConnectNotification('ended', ['name' => $by->first_name], '/connect/mentorships/'.$m->id));
    }

    public function feedback(Mentorship $m, User $by, string $feedback): void
    {
        $m->forceFill([$by->id === $m->mentor_id ? 'mentor_feedback' : 'mentee_feedback' => mb_substr($feedback, 0, 1000)])->save();
    }

    public function renew(Mentorship $m, User $by): void
    {
        if ($m->status !== 'active' || $m->renewed) {
            throw new DomainException(__('connect.agreement.renew_once'));
        }
        $m->forceFill(['renewed' => true, 'ends_on' => ($m->ends_on ?? now())->addMonths($m->months)->toDateString()])->save();
        $this->audit->record('connect.renewed', meta: ['mentorship' => $m->id], actor: $by);
    }

    private function informGuardian(User $minor, User $mentor): void
    {
        $guardian = GuardianConsent::query()->where('user_id', $minor->id)->whereNotNull('verified_at')->latest('verified_at')->first();
        if ($guardian !== null) {
            app(SmsSender::class)->send($guardian->guardian_phone, __('connect.guardian_sms', ['name' => $minor->first_name, 'mentor' => $mentor->first_name.' '.mb_substr((string) $mentor->last_name, 0, 1).'.']));
            $this->audit->record('connect.guardian_informed', $minor);
        }
    }
}
