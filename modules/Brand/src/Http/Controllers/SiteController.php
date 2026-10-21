<?php

declare(strict_types=1);

namespace Modules\Brand\Http\Controllers;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Brand\Http\Concerns\ResolvesBusiness;
use Modules\Brand\Services\Calendar;
use Modules\Brand\Services\Designs;
use Modules\Brand\Services\Sites;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;

/** KasiBrand part 2 (S31): the business website and the content calendar; moderators' take-down. */
final class SiteController
{
    use ResolvesBusiness;

    public function __construct(private readonly Sites $sites, private readonly Calendar $calendar) {}

    public function site(Request $request): Response
    {
        [$person] = $this->who($request);
        $b = $this->business($request, $person);
        $site = $this->sites->site($b['id']);

        return Inertia::render('Brand/Site', ['business' => $b, 'site' => $site, 'url' => ($site['name'] ?? null) !== null ? Sites::url((string) $site['name']) : null,
            'suggested' => Str::slug($b['name']) ?: null, 'domain' => (string) config('kasi.subdomains.domain'),
            'idVerified' => DB::table('documents')->where('user_id', $person->id)->where('type', 'id_document')->where('status', 'verified')->exists(),
            'photos' => DB::table('brand_site_photos')->where('business_id', $b['id'])->orderBy('position')->get(['id', 'path', 'alt'])
                ->map(static fn (object $p): array => ['id' => (int) $p->id, 'url' => asset('storage/'.$p->path), 'alt' => (string) $p->alt]),
            'stats' => Sites::stats($b['id']), 'maxPhotos' => Sites::MAX_PHOTOS, 'maxServices' => Sites::MAX_SERVICES]);
    }

    public function check(Request $request): JsonResponse
    {
        [$person] = $this->who($request);
        $b = $this->business($request, $person);
        $name = strtolower(trim((string) $request->query('name', '')));
        $problem = $this->sites->nameProblem($name, $b['id']);

        return response()->json(['ok' => $problem === null, 'message' => $problem !== null ? __('brand.site.name_'.$problem) : __('brand.site.name_free')]);
    }

    public function save(Request $request): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $b = $this->business($request, $person);
        $data = $request->validate(['name' => ['nullable', 'string', 'max:30'], 'about' => ['nullable', 'string', 'max:600'], 'use_market' => ['boolean'],
            'services' => ['array', 'max:'.Sites::MAX_SERVICES], 'services.*.name' => ['nullable', 'string', 'max:60'], 'services.*.price' => ['nullable', 'string', 'max:20']]);

        return $this->attempt(fn () => $this->sites->save($b['id'], $helper ?? $person, $data), 'brand.site.saved');
    }

    public function photo(Request $request): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $b = $this->business($request, $person);
        $data = $request->validate(['photo' => ['required', File::image()->max(8 * 1024)], 'alt' => ['required', 'string', 'max:120']]);

        return $this->attempt(fn () => $this->sites->addPhoto($b['id'], $helper ?? $person, $request->file('photo'), $data['alt']), 'brand.site.photo_added');
    }

    public function removePhoto(Request $request, int $photo): RedirectResponse
    {
        [$person] = $this->who($request);
        $b = $this->business($request, $person);
        $this->sites->removePhoto($b['id'], $photo);

        return back();
    }

    public function publish(Request $request): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $b = $this->business($request, $person);
        if ($request->boolean('unpublish')) {
            $this->sites->unpublish($b['id'], $helper ?? $person);

            return back()->with('status', __('brand.site.unpublished'));
        }
        try {
            $status = $this->sites->publish($b['id'], $person, $helper ?? $person);
        } catch (DomainException $e) {
            return back()->withErrors(['brand' => $e->getMessage()]);
        }

        return back()->with('status', __($status === 'published' ? 'brand.site.published' : 'brand.site.held'));
    }

    public function calendar(Request $request): Response
    {
        [$person] = $this->who($request);
        $b = $this->business($request, $person);
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) === 1 ? CarbonImmutable::parse($request->query('month').'-01') : now()->toImmutable()->startOfMonth();

        return Inertia::render('Brand/Calendar', ['business' => $b, 'month' => $month->format('Y-m'), 'channels' => Calendar::CHANNELS, 'templates' => array_keys(Designs::TEMPLATES),
            'posts' => DB::table('brand_posts')->where('business_id', $b['id'])->whereBetween('planned_at', [$month->toDateTimeString(), $month->endOfMonth()->toDateTimeString()])
                ->orderBy('planned_at')->get(['id', 'channel', 'planned_at', 'text', 'design_id', 'template', 'status', 'suggested']),
            'designs' => DB::table('brand_designs')->where('business_id', $b['id'])->latest('updated_at')->limit(30)->get(['id', 'title', 'template']),
            'moments' => Calendar::moments($month->year, $month->month)]);
    }

    public function plan(Request $request): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $b = $this->business($request, $person);
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) === 1 ? CarbonImmutable::parse($request->input('month').'-01') : now()->toImmutable()->startOfMonth();
        $n = $this->calendar->planMonth($b['id'], $helper ?? $person, $month->year, $month->month, $helper !== null ? $person : null);

        return back()->with('status', trans_choice('brand.calendar.planned', $n, ['count' => $n]));
    }

    public function savePost(Request $request, ?int $post = null): RedirectResponse
    {
        [$person] = $this->who($request);
        $b = $this->business($request, $person);
        $data = $request->validate(['channel' => ['required', Rule::in(Calendar::CHANNELS)], 'planned_at' => ['required', 'date'], 'text' => ['required', 'string', 'max:500'],
            'design_id' => ['nullable', 'integer']]);

        return $this->attempt(fn () => $this->calendar->save($b['id'], $data, $post), 'brand.calendar.saved');
    }

    public function markPost(Request $request, int $post): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $b = $this->business($request, $person);
        $status = (string) $request->input('status');
        if ($status === 'delete') {
            $this->calendar->delete($b['id'], $post);

            return back();
        }

        return $this->attempt(fn () => $status === 'posted' ? $this->calendar->posted($b['id'], $post, $helper ?? $person, $helper !== null ? $person : null)
            : $this->calendar->mark($b['id'], $post, $status), 'brand.calendar.updated');
    }

    /** Moderators: a website's details and take-down / restore (PIN confirmed). */
    public function moderate(string $name): Response
    {
        $site = DB::table('brand_sites')->where('name', $name)->first();
        abort_if($site === null, 404);

        return Inertia::render('Brand/Moderate', ['name' => $name, 'status' => (string) $site->status, 'reason' => $site->held_reason, 'url' => Sites::url($name),
            'business' => (string) (app(BusinessFacts::class)->facts((string) $site->business_id)['name'] ?? '')]);
    }

    public function takeDown(Request $request, string $name): RedirectResponse
    {
        $businessId = DB::table('brand_sites')->where('name', $name)->value('business_id');
        abort_unless(is_string($businessId), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300'], 'restore' => ['boolean']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->sites->takeDown($businessId, $user, $data['reason'], (bool) ($data['restore'] ?? false));

        return back()->with('status', __('brand.site.moderated'));
    }

    private function attempt(callable $fn, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (DomainException $e) {
            return back()->withErrors(['brand' => $e->getMessage()]);
        }

        return back()->with('status', __($ok));
    }
}
