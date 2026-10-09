<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use App\Support\Format\SaFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Work\Models\JobListing;
use Modules\Work\Services\CurrentEmployer;
use Modules\Work\Services\EmployerRegistration;
use Modules\Work\Services\WorkSubject;

/**
 * Employers: register the business, dashboard of listings, company profile and team.
 */
final class EmployerController extends WorkController
{
    public const SECTORS = ['retail', 'hospitality', 'agriculture', 'construction', 'cleaning', 'security', 'transport', 'manufacturing', 'office', 'call_centre', 'health_care', 'education', 'beauty', 'it', 'other'];

    public const SIZES = ['1', '2-10', '11-50', '51-200', '200+'];

    public function __construct(WorkSubject $subject, private readonly CurrentEmployer $employers)
    {
        parent::__construct($subject);
    }

    public function dashboard(Request $request): Response|RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $employer = $this->employers->resolve($request, $user);
        if ($employer === null) {
            return to_route('work.employer.register');
        }

        $views = DB::table('work_listing_views')->selectRaw('listing_id, sum(views) as total')->groupBy('listing_id')->pluck('total', 'listing_id');
        $saves = DB::table('work_saved_jobs')->selectRaw('listing_id, count(*) as total')->groupBy('listing_id')->pluck('total', 'listing_id');

        return Inertia::render('Work/Employer/Dashboard', [
            'person' => ['name' => $user->fullName(), 'assisted' => $by !== null],
            'employer' => $this->employerProps($employer),
            'employers' => $this->employers->all($user)->map(static fn (Organisation $o): array => ['id' => $o->id, 'name' => $o->displayName()])->values(),
            'isAdmin' => $this->employers->isAdmin($user, $employer),
            'listings' => JobListing::query()->where('organisation_id', $employer->id)->latest('created_at')->limit(50)->get()
                ->map(static fn (JobListing $l): array => [
                    'id' => $l->id, 'title' => $l->title, 'status' => $l->status, 'reason' => $l->status_reason,
                    'closesOn' => $l->closes_on->toDateString(), 'views' => (int) ($views[$l->id] ?? 0), 'saves' => (int) ($saves[$l->id] ?? 0),
                ]),
            'team' => RoleAssignment::query()->with('user:id,first_name,last_name,phone')->where('scope_type', 'organisation')->where('scope_id', $employer->id)
                ->whereIn('role', ['employer_admin', 'recruiter'])->get()
                ->map(static fn (RoleAssignment $a): array => ['userId' => $a->user_id, 'name' => $a->user?->fullName(), 'phone' => $a->user ? SaFormat::maskedPhone($a->user->phone) : null, 'role' => $a->role]),
            'limit' => (int) config($employer->community ? 'kasi.work.community_listing_limit' : 'kasi.work.free_listing_limit'),
            'options' => ['sectors' => self::SECTORS, 'sizes' => self::SIZES],
        ]);
    }

    public function registerForm(Request $request): Response
    {
        [$user, $by] = $this->who($request);

        return Inertia::render('Work/Employer/Register', [
            'person' => ['name' => $user->fullName(), 'assisted' => $by !== null],
            'cities' => Municipality::query()->where('category', '!=', 'district')->orderBy('name')->get(['id', 'name']),
            'options' => ['sectors' => self::SECTORS, 'sizes' => self::SIZES],
            'defaultCity' => $user->municipality_id,
        ]);
    }

    public function register(Request $request, EmployerRegistration $registration): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $community = $request->boolean('community');
        $data = $request->validate([
            'community' => ['boolean'],
            'name' => ['required', 'string', 'max:160'],
            'trading_name' => ['nullable', 'string', 'max:160'],
            'registration_number' => [Rule::requiredIf(! $community), 'nullable', 'string', 'max:20', function (string $a, mixed $v, \Closure $fail) use ($community): void {
                if (! $community && is_string($v) && ! EmployerRegistration::validCipcNumber($v)) {
                    $fail(__('work.employer.cipc_format'));
                }
            }],
            'certificate' => [Rule::requiredIf(! $community), 'nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)],
            'sector' => ['required', Rule::in(self::SECTORS)],
            'size_band' => ['required', Rule::in(self::SIZES)],
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->whereNot('category', 'district')],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email:rfc', 'max:190'],
            'description' => ['nullable', 'string', 'max:1000'],
            'title' => ['nullable', 'string', 'max:80'],
            'confirm' => ['accepted'],
        ]);

        $registration->register($user, $data, $request->file('certificate'), $by);

        return to_route('work.employer')->with('status', __($community ? 'work.employer.registered_community' : 'work.employer.registered'));
    }

    public function updateProfile(Request $request, AuditLogger $audit): RedirectResponse
    {
        [$user, $by, $employer] = $this->adminOf($request);
        $data = $request->validate([
            'trading_name' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'url:https,http', 'max:190'],
            'contact_email' => ['nullable', 'email:rfc', 'max:190'],
            'sector' => ['required', Rule::in(self::SECTORS)],
            'size_band' => ['required', Rule::in(self::SIZES)],
        ]);
        $employer->update($data);
        $audit->record('work.employer_profile_updated', meta: ['organisation' => $employer->id], actor: $by ?? $user);

        return back()->with('status', __('work.saved'));
    }

    public function addRecruiter(Request $request, EmployerRegistration $registration): RedirectResponse
    {
        [$user, $by, $employer] = $this->adminOf($request);
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = SaFormat::normalisePhone($data['phone']);
        $person = $phone !== null ? User::query()->where('phone', $phone)->first() : null;

        if ($person === null || $person->isMinor()) {
            return back()->withErrors(['phone' => __('work.team.no_account')]);
        }

        try {
            $registration->addRecruiter($employer, $person, $by ?? $user);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        return back()->with('status', __('work.team.added', ['name' => $person->fullName()]));
    }

    public function removeRecruiter(Request $request, User $member, EmployerRegistration $registration): RedirectResponse
    {
        [$user, $by, $employer] = $this->adminOf($request);
        abort_if($member->id === $user->id, 403);
        $registration->removeRecruiter($employer, $member, $by ?? $user);

        return back()->with('status', __('work.team.removed'));
    }

    /** @return array{0: User, 1: User|null, 2: Organisation} */
    private function adminOf(Request $request): array
    {
        [$user, $by] = $this->who($request);
        $employer = $this->employers->resolve($request, $user);
        abort_unless($employer !== null && $this->employers->isAdmin($user, $employer), 403);

        return [$user, $by, $employer];
    }

    /** @return array<string, mixed> */
    private function employerProps(Organisation $o): array
    {
        return [
            'id' => $o->id, 'name' => $o->name, 'tradingName' => $o->trading_name, 'status' => $o->verification_status, 'community' => $o->community,
            'sector' => $o->sector, 'sizeBand' => $o->size_band, 'description' => $o->description, 'website' => $o->website, 'email' => $o->contact_email,
        ];
    }
}
