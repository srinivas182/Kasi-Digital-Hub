<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use App\Support\Format\SaFormat;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Structure\Models\Organisation;
use Modules\Work\Matching\Invitations;
use Modules\Work\Matching\Visibility;
use Modules\Work\Models\JobListing;

/**
 * "Jobs for you": matches with reasons, invitations to apply, employers who viewed the profile,
 * and employers the person hides from.
 */
final class MatchesController extends WorkController
{
    public function index(Request $request, Visibility $visibility): Response
    {
        [$user, $by] = $this->who($request);
        $tab = in_array($request->query('tab'), ['matches', 'invitations', 'views', 'hidden'], true) ? (string) $request->query('tab') : 'matches';

        $matches = DB::table('work_matches')->join('work_listings', 'work_listings.id', '=', 'work_matches.listing_id')
            ->join('organisations', 'organisations.id', '=', 'work_listings.organisation_id')
            ->where('work_matches.user_id', $user->id)->where('work_listings.status', 'live')
            ->orderByDesc('work_matches.score')->limit(50)
            ->get(['work_listings.id', 'work_listings.title', 'work_listings.type', 'work_listings.place_name', 'work_listings.pay_min_cents', 'work_listings.pay_period',
                'organisations.name as employer', 'organisations.trading_name', 'work_matches.score', 'work_matches.reasons', 'work_matches.gaps', 'work_matches.distance_km'])
            ->map(static fn (object $m): array => [
                'id' => $m->id, 'title' => $m->title, 'type' => $m->type, 'place' => $m->place_name, 'employer' => $m->trading_name ?: $m->employer,
                'pay' => SaFormat::money((int) $m->pay_min_cents).' '.__('work.pay.per_'.$m->pay_period),
                'score' => (int) $m->score, 'distanceKm' => $m->distance_km !== null ? (int) $m->distance_km : null,
                'reasons' => self::explain((string) $m->reasons), 'gaps' => self::explain((string) $m->gaps),
            ]);

        return Inertia::render('Work/Matches', [
            'tab' => $tab,
            'person' => ['name' => $user->fullName(), 'assisted' => $by !== null],
            'visible' => $visibility->consenting([$user->id]) !== [],
            'matches' => $matches,
            'invitations' => DB::table('work_invitations')->join('work_listings', 'work_listings.id', '=', 'work_invitations.listing_id')
                ->join('organisations', 'organisations.id', '=', 'work_listings.organisation_id')
                ->where('work_invitations.user_id', $user->id)->orderByDesc('work_invitations.created_at')->limit(30)
                ->get(['work_invitations.id', 'work_invitations.status', 'work_invitations.message', 'work_invitations.created_at', 'work_listings.id as listing_id',
                    'work_listings.title', 'organisations.name as employer', 'organisations.trading_name'])
                ->map(static fn (object $i): array => ['id' => $i->id, 'status' => $i->status, 'message' => $i->message, 'at' => (string) $i->created_at,
                    'listingId' => $i->listing_id, 'title' => $i->title, 'employer' => $i->trading_name ?: $i->employer]),
            'views' => DB::table('work_profile_views')->join('organisations', 'organisations.id', '=', 'work_profile_views.organisation_id')
                ->where('work_profile_views.user_id', $user->id)->orderByDesc('viewed_at')->limit(50)
                ->get(['organisations.id as organisationId', 'organisations.name', 'organisations.trading_name', 'work_profile_views.viewed_at'])
                ->map(static fn (object $v): array => ['organisationId' => $v->organisationId, 'employer' => $v->trading_name ?: $v->name, 'at' => (string) $v->viewed_at]),
            'hidden' => DB::table('work_hidden_employers')->join('organisations', 'organisations.id', '=', 'work_hidden_employers.organisation_id')
                ->where('work_hidden_employers.user_id', $user->id)->get(['organisations.id', 'organisations.name', 'organisations.trading_name'])
                ->map(static fn (object $o): array => ['id' => $o->id, 'name' => $o->trading_name ?: $o->name]),
        ]);
    }

    public function answer(Request $request, string $invitation, Invitations $invitations): RedirectResponse
    {
        [$user] = $this->who($request);
        $data = $request->validate(['accept' => ['required', 'boolean']]);

        try {
            $invitations->answer($invitation, $user, (bool) $data['accept']);
        } catch (DomainException $e) {
            return back()->withErrors(['invitation' => $e->getMessage()]);
        }

        return back()->with('status', __($data['accept'] ? 'work.invite.accepted' : 'work.invite.declined'));
    }

    public function hide(Request $request): RedirectResponse
    {
        [$user] = $this->who($request);
        $data = $request->validate(['organisation_id' => ['required', 'string', 'exists:organisations,id']]);
        DB::table('work_hidden_employers')->insertOrIgnore(['user_id' => $user->id, 'organisation_id' => $data['organisation_id'], 'created_at' => now()]);

        return back()->with('status', __('work.hide.hidden', ['name' => Organisation::query()->whereKey($data['organisation_id'])->first()?->displayName()]));
    }

    public function unhide(Request $request, Organisation $organisation): RedirectResponse
    {
        [$user] = $this->who($request);
        DB::table('work_hidden_employers')->where('user_id', $user->id)->where('organisation_id', $organisation->id)->delete();

        return back()->with('status', __('work.hide.shown'));
    }

    /** Hide from the employer of a job (from the job page). */
    public function hideEmployerOf(Request $request, JobListing $listing): RedirectResponse
    {
        $request->merge(['organisation_id' => $listing->organisation_id]);

        return $this->hide($request);
    }

    /**
     * @return list<string>
     */
    public static function explain(string $json): array
    {
        return array_values(array_map(static fn (array $r): string => (string) __($r['key'], array_map('strval', $r['params'] ?? [])), (array) json_decode($json, true)));
    }
}
