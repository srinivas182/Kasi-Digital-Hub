<?php

declare(strict_types=1);

namespace Modules\HubOps\Http\Controllers;

use App\Support\Format\SaFormat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Assist\AssistedSession;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\HubOps\Models\HubVisit;
use Modules\HubOps\Services\CheckIns;

/**
 * Front desk: check people in (lookup or walk-in), today's visits, members and follow-up lists.
 */
final class DeskController extends StaffController
{
    public function checkIn(Request $request): Response
    {
        $hub = $this->hub($request);
        $q = trim((string) $request->query('q', ''));
        $today = now('Africa/Johannesburg')->toDateString();

        return Inertia::render('HubOps/CheckIn', [
            'hubs' => $this->hubProps($request, $hub),
            'q' => $q,
            'results' => mb_strlen($q) >= 3 ? $this->search($q)->limit(10)->get()->map(fn (User $u): array => $this->personRow($u, $hub->id)) : [],
            'today' => HubVisit::query()->with('user:id,first_name,last_name')->where('hub_id', $hub->id)->whereDate('visit_date', $today)
                ->latest('created_at')->limit(50)->get()
                ->map(static fn (HubVisit $v): array => [
                    'id' => $v->id, 'name' => $v->user?->fullName(), 'personId' => $v->user_id, 'purpose' => $v->purpose,
                    'method' => $v->method, 'at' => $v->created_at->toIso8601String(),
                ]),
            'purposes' => HubVisit::PURPOSES,
        ]);
    }

    public function store(Request $request, CheckIns $checkIns): RedirectResponse
    {
        $hub = $this->hub($request);
        $validated = $request->validate([
            'person_id' => ['nullable', 'string', 'exists:users,id'],
            'purpose' => ['required', Rule::in(HubVisit::PURPOSES)],
        ]);

        $person = isset($validated['person_id']) ? User::query()->whereKey($validated['person_id'])->firstOrFail() : null;
        $result = $checkIns->record($hub, $person, $validated['purpose'], $person === null ? 'walk_in' : 'desk', $this->actor($request));

        return back()->with('status', $result['created'] ? __('hubops.checkin.done') : __('hubops.checkin.already'));
    }

    public function members(Request $request): Response
    {
        $hub = $this->hub($request);
        $filter = in_array($request->query('filter'), ['no_id', 'inactive'], true) ? (string) $request->query('filter') : 'all';
        $lastVisit = HubVisit::query()->selectRaw('max(visit_date)')->whereColumn('hub_visits.user_id', 'users.id')->where('hub_id', $hub->id);

        $members = User::query()->where('home_hub_id', $hub->id)
            ->select('users.*')->selectSub($lastVisit, 'last_visit')
            ->when($filter === 'no_id', fn (Builder $q) => $q->whereNotExists(fn ($d) => $d->from('documents')->whereColumn('documents.user_id', 'users.id')
                ->where('type', 'id_document')->whereIn('status', [Document::UPLOADED, Document::VERIFIED])))
            ->when($filter === 'inactive', fn (Builder $q) => $q->whereNotExists(fn ($v) => $v->from('hub_visits')->whereColumn('hub_visits.user_id', 'users.id')
                ->where('hub_id', $hub->id)->where('visit_date', '>=', now('Africa/Johannesburg')->subDays(30)->toDateString())))
            ->orderBy('first_name')->orderBy('last_name')
            ->paginate(25)->withQueryString();

        return Inertia::render('HubOps/Members', [
            'hubs' => $this->hubProps($request, $hub),
            'filter' => $filter,
            'members' => $members->through(static fn (User $u): array => [
                'id' => $u->id, 'name' => $u->fullName(), 'phone' => SaFormat::maskedPhone($u->phone),
                'lastVisit' => $u->getAttribute('last_visit') !== null ? substr((string) $u->getAttribute('last_visit'), 0, 10) : null,
                'joined' => $u->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /** Start helping a person who is at the hub today. */
    public function startAssist(Request $request, User $person, CheckIns $checkIns, AssistedSession $assist): RedirectResponse
    {
        $hub = $this->hub($request);

        if (! $checkIns->visitedToday($hub, $person)) {
            return back()->withErrors(['assist' => __('hubops.assist.check_in_first')]);
        }

        $assist->start($this->actor($request), $person, $hub);

        return to_route('hubops.assist.show');
    }

    /** @return Builder<User> */
    private function search(string $q): Builder
    {
        $digits = preg_replace('/\D/', '', $q) ?? '';

        return User::query()->where(function (Builder $w) use ($q, $digits): void {
            if (strlen($digits) >= 4) {
                $w->where('phone', 'like', '%'.$digits);
            }
            foreach (preg_split('/\s+/', $q) ?: [] as $word) {
                if ($word !== '' && ! ctype_digit($word)) {
                    $w->orWhere('first_name', 'like', $word.'%')->orWhere('last_name', 'like', $word.'%')->orWhere('preferred_name', 'like', $word.'%');
                }
            }
        })->orderBy('first_name');
    }

    /** @return array{id: string, name: string, phone: string, homeHub: string|null, here: bool, minor: bool} */
    private function personRow(User $u, string $hubId): array
    {
        return [
            'id' => $u->id, 'name' => $u->fullName(), 'phone' => SaFormat::maskedPhone($u->phone), 'homeHub' => $u->homeHub?->name,
            'here' => HubVisit::query()->where('hub_id', $hubId)->where('user_id', $u->id)->whereDate('visit_date', now('Africa/Johannesburg')->toDateString())->exists(),
            'minor' => $u->isMinor(),
        ];
    }
}
