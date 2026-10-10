<?php

declare(strict_types=1);

namespace Modules\Learn\Http\Controllers;

use App\Support\Format\SaFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Core\Structure\OrganisationRegistration;
use Modules\Learn\Events\ProviderRegistered;
use Modules\Learn\Models\Accreditation;
use Modules\Learn\Models\Certificate;
use Modules\Learn\Models\Course;
use Modules\Learn\Services\CurrentProvider;

/**
 * Training providers: register, dashboard of courses, team (authors, assessors) and accreditation claims.
 */
final class ProviderController
{
    public function __construct(private readonly CurrentProvider $providers) {}

    public function dashboard(Request $request): Response|RedirectResponse
    {
        $user = $this->adult($request);
        $provider = $this->providers->resolve($request, $user);
        if ($provider === null) {
            return to_route('learn.provider.register');
        }
        $isAdmin = $this->providers->has($user, $provider, 'provider_admin');

        return Inertia::render('Learn/Provider/Dashboard', [
            'provider' => ['id' => $provider->id, 'name' => $provider->displayName(), 'status' => $provider->verification_status],
            'providers' => $this->providers->all($user)->map(static fn (Organisation $o): array => ['id' => $o->id, 'name' => $o->displayName()])->values(),
            'isAdmin' => $isAdmin,
            'canAuthor' => $this->providers->canAuthor($user, $provider),
            'courses' => Course::query()->where('organisation_id', $provider->id)->latest('updated_at')->get()
                ->map(static fn (Course $c): array => ['id' => $c->id, 'title' => $c->title, 'status' => $c->status, 'changed' => $c->changed_since_publish, 'slug' => $c->slug, 'reason' => $c->status_reason]),
            'team' => RoleAssignment::query()->with('user:id,first_name,last_name,phone')->where('scope_type', 'organisation')->where('scope_id', $provider->id)
                ->whereIn('role', CurrentProvider::ROLES)->get()
                ->map(static fn (RoleAssignment $a): array => ['userId' => $a->user_id, 'name' => $a->user?->fullName(), 'phone' => $a->user ? SaFormat::maskedPhone($a->user->phone) : null, 'role' => $a->role]),
            'accreditations' => Accreditation::query()->where('organisation_id', $provider->id)->get(['id', 'body', 'number', 'status', 'reason']),
            'signatory' => DB::table('learn_provider_settings')->where('organisation_id', $provider->id)->first(['signatory_name', 'signatory_title']),
            'certificates' => $isAdmin ? Certificate::query()->with(['enrolment.course', 'document'])
                ->whereHas('enrolment.course', fn ($q) => $q->where('organisation_id', $provider->id))->latest('issued_at')->limit(50)->get()
                ->map(static fn (Certificate $c): array => ['id' => $c->id, 'course' => $c->enrolment->course->title, 'code' => $c->document?->verification_code,
                    'issuedAt' => $c->issued_at->toIso8601String(), 'revoked' => $c->revoked_at !== null]) : [],
        ]);
    }

    public function registerForm(Request $request): Response
    {
        $user = $this->adult($request);

        return Inertia::render('Learn/Provider/Register', [
            'cities' => Municipality::query()->where('category', '!=', 'district')->orderBy('name')->get(['id', 'name']),
            'defaultCity' => $user->municipality_id,
        ]);
    }

    public function register(Request $request, OrganisationRegistration $registration): RedirectResponse
    {
        $user = $this->adult($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'trading_name' => ['nullable', 'string', 'max:160'],
            'registration_number' => ['required', 'string', 'max:20', function (string $a, mixed $v, \Closure $fail): void {
                if (! is_string($v) || ! OrganisationRegistration::validCipcNumber($v)) {
                    $fail(__('work.employer.cipc_format'));
                }
            }],
            'certificate' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)],
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->whereNot('category', 'district')],
            'contact_email' => ['nullable', 'email:rfc', 'max:190'],
            'description' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
        ]);

        $provider = $registration->register($user, 'training_provider', 'provider_admin', [...$data, 'sector' => 'education'], $request->file('certificate'));
        event(new ProviderRegistered($user, null, ['organisation' => $provider->id]));

        return to_route('learn.provider')->with('status', __('learn.provider.registered'));
    }

    public function addMember(Request $request, OrganisationRegistration $registration): RedirectResponse
    {
        [$user, $provider] = $this->admin($request);
        $data = $request->validate(['phone' => ['required', 'string', 'max:20'], 'role' => ['required', Rule::in(['course_author', 'assessor_moderator', 'provider_admin'])]]);
        $phone = SaFormat::normalisePhone($data['phone']);
        $person = $phone !== null ? User::query()->where('phone', $phone)->first() : null;
        if ($person === null || $person->isMinor()) {
            return back()->withErrors(['phone' => __('work.team.no_account')]);
        }

        try {
            $registration->addMember($provider, $person, $data['role'], $user, __('learn.role.'.$data['role']));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        return back()->with('status', __('work.team.added', ['name' => $person->fullName()]));
    }

    public function removeMember(Request $request, User $member, string $role, OrganisationRegistration $registration): RedirectResponse
    {
        [$user, $provider] = $this->admin($request);
        abort_if($member->id === $user->id || ! in_array($role, CurrentProvider::ROLES, true), 403);
        $registration->removeMember($provider, $member, $role, $user);

        return back()->with('status', __('work.team.removed'));
    }

    public function signatory(Request $request): RedirectResponse
    {
        [, $provider] = $this->admin($request);
        $data = $request->validate(['signatory_name' => ['nullable', 'string', 'max:120'], 'signatory_title' => ['nullable', 'string', 'max:120']]);
        DB::table('learn_provider_settings')->updateOrInsert(['organisation_id' => $provider->id], [...$data, 'updated_at' => now(), 'created_at' => now()]);

        return back()->with('status', __('work.saved'));
    }

    /** Claim an accreditation (QCTO or a SETA) with evidence; the KasiHub team verifies it. */
    public function claimAccreditation(Request $request, DocumentVault $vault): RedirectResponse
    {
        [$user, $provider] = $this->admin($request);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:80'],
            'number' => ['required', 'string', 'max:60'],
            'evidence' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)],
        ]);
        $document = $vault->store($user, $request->file('evidence'), 'qualification');
        Accreditation::query()->create(['organisation_id' => $provider->id, 'body' => $data['body'], 'number' => $data['number'], 'document_id' => $document->id]);

        return back()->with('status', __('learn.accreditation.claimed'));
    }

    private function adult(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_if($user->isMinor(), 403);

        return $user;
    }

    /** @return array{0: User, 1: Organisation} */
    private function admin(Request $request): array
    {
        $user = $this->adult($request);
        $provider = $this->providers->resolve($request, $user);
        abort_unless($provider !== null && $this->providers->has($user, $provider, 'provider_admin'), 403);

        return [$user, $provider];
    }
}
