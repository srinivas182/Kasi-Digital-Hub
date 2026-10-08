<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Support\Format\SaFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Services\OrganisationVerification;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;

/**
 * Organisations: list, verification queue, details, members.
 */
final class OrganisationsController
{
    use Concerns;

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: null;
        $type = $request->string('type')->toString() ?: null;

        return Inertia::render('Admin/Organisations/Index', [
            'filters' => ['status' => $status, 'type' => $type],
            'organisations' => Organisation::query()
                ->when($status, fn ($q) => $q->where('verification_status', $status))
                ->when($type, fn ($q) => $q->where('type', $type))
                ->withCount('members')
                ->orderByRaw("case when verification_status = 'pending' then 0 else 1 end")
                ->orderBy('name')
                ->paginate(25)->withQueryString()
                ->through(static fn (Organisation $o): array => [
                    'id' => $o->id, 'name' => $o->name, 'type' => $o->type, 'status' => $o->verification_status,
                    'members' => $o->members_count, 'registration' => $o->registration_number, 'created' => $o->created_at?->toIso8601String(),
                ]),
            'types' => Organisation::TYPES,
            'canManage' => $this->can($request, 'admin.organisations.manage'),
        ]);
    }

    public function show(Request $request, Organisation $organisation): Response
    {
        return $this->form($request, $organisation);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $organisation = Organisation::query()->create([...$this->validated($request), 'verification_status' => 'pending']);
        $audit->record('organisation.created', meta: ['organisation' => $organisation->id], actor: $this->actor($request));

        return to_route('admin.organisations.show', $organisation)->with('status', __('admin.organisations.created'));
    }

    public function update(Request $request, Organisation $organisation, AuditLogger $audit): RedirectResponse
    {
        $organisation->update($this->validated($request));
        $audit->record('organisation.updated', meta: ['organisation' => $organisation->id, 'changed' => array_keys($organisation->getChanges())], actor: $this->actor($request));

        return back()->with('status', __('admin.saved'));
    }

    public function verify(Request $request, Organisation $organisation, OrganisationVerification $verification): RedirectResponse
    {
        $verification->verify($organisation, $this->actor($request));

        return back()->with('status', __('admin.organisations.verified'));
    }

    public function reject(Request $request, Organisation $organisation, OrganisationVerification $verification): RedirectResponse
    {
        $verification->reject($organisation, $this->validateReason($request)['reason'], $this->actor($request));

        return back()->with('status', __('admin.organisations.rejected'));
    }

    public function addMember(Request $request, Organisation $organisation, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:20'], 'title' => ['nullable', 'string', 'max:80']]);
        $phone = SaFormat::normalisePhone($validated['phone']);
        $member = $phone !== null ? User::query()->where('phone', $phone)->first() : null;

        if ($member === null) {
            return back()->withErrors(['phone' => __('admin.organisations.no_account')]);
        }

        $organisation->members()->syncWithoutDetaching([$member->id => ['title' => $validated['title'] ?? null]]);
        $audit->record('organisation.member_added', $member, meta: ['organisation' => $organisation->id], actor: $this->actor($request));

        return back()->with('status', __('admin.organisations.member_added'));
    }

    public function removeMember(Request $request, Organisation $organisation, User $member, AuditLogger $audit): RedirectResponse
    {
        $organisation->members()->detach($member->id);
        $audit->record('organisation.member_removed', $member, meta: ['organisation' => $organisation->id], actor: $this->actor($request));

        return back()->with('status', __('admin.organisations.member_removed'));
    }

    private function form(Request $request, ?Organisation $organisation): Response
    {
        return Inertia::render('Admin/Organisations/Show', [
            'organisation' => $organisation === null ? null : [
                'id' => $organisation->id, 'name' => $organisation->name, 'type' => $organisation->type,
                'registrationNumber' => $organisation->registration_number, 'status' => $organisation->verification_status,
                'verifiedAt' => $organisation->verified_at?->toIso8601String(), 'email' => $organisation->contact_email,
                'phone' => $organisation->contact_phone, 'municipalityId' => $organisation->municipality_id, 'address' => $organisation->address,
            ],
            'members' => $organisation === null ? [] : $organisation->members()->get()->map(static fn (User $m): array => [
                'id' => $m->id, 'name' => $m->fullName(), 'phone' => SaFormat::maskedPhone($m->phone), 'title' => $m->getRelationValue('pivot')?->getAttribute('title'),
            ]),
            'types' => Organisation::TYPES,
            'cities' => Municipality::query()->where('category', '!=', 'district')->orderBy('name')->get(['id', 'name']),
            'can' => [
                'manage' => $this->can($request, 'admin.organisations.manage'),
                'verify' => $this->can($request, 'admin.organisations.verify'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(Organisation::TYPES)],
            'registration_number' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email:rfc', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $data['contact_phone'] = isset($data['contact_phone']) ? SaFormat::normalisePhone((string) $data['contact_phone']) : null;

        return $data;
    }
}
