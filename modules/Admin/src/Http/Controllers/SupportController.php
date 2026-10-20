<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Support\Format\SaFormat;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Support\Support;
use Modules\Core\Identity\Models\User;

/**
 * Support for people (their help requests) and for staff (queue, safe summaries, account help).
 */
final class SupportController
{
    public function __construct(private readonly Support $support) {}

    // --- People -----------------------------------------------------------------------------

    public function mine(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Admin/Support/Mine', [
            'tickets' => DB::table('support_tickets')->where('user_id', $user->id)->latest('created_at')->get(['id', 'number', 'subject', 'status', 'created_at']),
            'categories' => Support::CATEGORIES,
            'subject' => mb_substr((string) $request->query('subject', ''), 0, 140), // e.g. "Order KM-2026-000123" from KasiMarket (S26)
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['category' => ['required', Rule::in(Support::CATEGORIES)], 'subject' => ['required', 'string', 'max:140'], 'body' => ['required', 'string', 'min:10', 'max:4000'],
            'screenshot' => ['nullable', File::image()->max(5 * 1024)]]);
        $id = $this->support->open($user, $data['category'], $data['subject'], $data['body'], $request->file('screenshot'));

        return to_route('help.request', $id)->with('status', __('admin.support.opened'));
    }

    public function showMine(Request $request, string $ticket): Response
    {
        $user = $this->user($request);
        $t = DB::table('support_tickets')->where('id', $ticket)->where('user_id', $user->id)->first();
        abort_if($t === null, 404);

        return Inertia::render('Admin/Support/Request', [
            'ticket' => (array) $t,
            'messages' => DB::table('support_messages')->where('ticket_id', $ticket)->where('internal', false)->orderBy('created_at')->get(['id', 'from_staff', 'body', 'created_at']),
        ]);
    }

    public function replyMine(Request $request, string $ticket): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless(DB::table('support_tickets')->where('id', $ticket)->where('user_id', $user->id)->exists(), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $this->support->message($ticket, $user, false, $data['body']);

        return back();
    }

    // --- Staff ------------------------------------------------------------------------------

    public function queue(Request $request): Response
    {
        $status = (string) $request->query('status', 'open');

        return Inertia::render('Admin/Support/Queue', [
            'status' => $status,
            'tickets' => DB::table('support_tickets')->leftJoin('users', 'users.id', '=', 'support_tickets.user_id')
                ->when($status !== 'all', fn ($q) => $q->where('support_tickets.status', $status))->orderBy('support_tickets.created_at')->limit(200)
                ->get(['support_tickets.id', 'number', 'category', 'subject', 'support_tickets.status', 'support_tickets.created_at', 'first_response_at', 'users.first_name'])
                ->map(static fn (object $t): array => [...(array) $t, 'overdue' => $t->first_response_at === null && Support::workingDaysSince(CarbonImmutable::parse((string) $t->created_at)) >= 2]),
            'counts' => DB::table('support_tickets')->groupBy('status')->selectRaw('status, count(*) as n')->pluck('n', 'status'),
        ]);
    }

    public function ticket(string $ticket): Response
    {
        $t = DB::table('support_tickets')->where('id', $ticket)->first();
        abort_if($t === null, 404);

        return Inertia::render('Admin/Support/Ticket', [
            'ticket' => (array) $t,
            'person' => $t->user_id !== null ? ['id' => (string) $t->user_id, 'name' => (string) DB::table('users')->where('id', $t->user_id)->value('first_name')] : null,
            'messages' => DB::table('support_messages')->leftJoin('users', 'users.id', '=', 'support_messages.sender_id')->where('ticket_id', $ticket)->orderBy('support_messages.created_at')
                ->get(['support_messages.id', 'from_staff', 'internal', 'body', 'support_messages.created_at', 'users.first_name']),
            'replies' => array_map(static fn (string $k): string => __("admin.support.saved.{$k}"), ['thanks', 'hub', 'pin', 'documents']),
        ]);
    }

    public function reply(Request $request, string $ticket): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000'], 'internal' => ['boolean'], 'status' => ['nullable', Rule::in(['open', 'waiting', 'resolved', 'escalated'])]]);
        $this->support->message($ticket, $user, true, $data['body'], (bool) ($data['internal'] ?? false));
        if (($data['status'] ?? null) !== null) {
            DB::table('support_tickets')->where('id', $ticket)->update(['status' => $data['status'], 'resolved_at' => $data['status'] === 'resolved' ? now() : null, 'assigned_to' => $user->id]);
        }

        return back()->with('status', __('admin.support.replied'));
    }

    /** A safe summary of a person: counts and statuses only; the reason is audited. */
    public function person(Request $request, User $person): Response
    {
        $staff = $this->user($request);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:200']]);

        return Inertia::render('Admin/Support/Person', ['summary' => $this->support->summary($person, $staff, $data['reason']), 'personId' => $person->id, 'reason' => $data['reason']]);
    }

    public function code(Request $request, User $person): RedirectResponse
    {
        $staff = $this->user($request);
        $data = $request->validate(['action' => ['required', Rule::in(['unlock', 'phone_change'])], 'new_phone' => ['nullable', 'required_if:action,phone_change', 'string', 'max:20']]);
        $to = null;
        if ($data['action'] === 'phone_change') {
            $to = SaFormat::normalisePhone((string) $data['new_phone']);
            if ($to === null) {
                return back()->withErrors(['new_phone' => __('validation.phone')]);
            }
        }
        $this->support->sendCode($person, $data['action'], $staff, $to);

        return back()->with('status', __('admin.support.code_sent'));
    }

    public function act(Request $request, User $person): RedirectResponse
    {
        $staff = $this->user($request);
        $data = $request->validate(['action' => ['required', Rule::in(['unlock', 'phone_change'])], 'code' => ['required', 'digits:6'], 'new_phone' => ['nullable', 'string', 'max:20'],
            'hub_check' => ['nullable', 'required_if:action,phone_change', 'string', 'min:10', 'max:300']]);

        try {
            $data['action'] === 'unlock' ? $this->support->unlock($person, $staff, $data['code'])
                : $this->support->changePhone($person, $staff, (string) SaFormat::normalisePhone((string) $data['new_phone']), $data['code'], (string) $data['hub_check']);
        } catch (DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return back()->with('status', __('admin.support.done'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
