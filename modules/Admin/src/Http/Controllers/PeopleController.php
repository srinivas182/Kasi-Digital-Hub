<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Support\Format\SaFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Admin\Services\AccountModeration;
use Modules\Admin\Services\PeopleDirectory;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\RoleRegistry;
use Modules\Core\Access\Scope;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\Consent;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Identity\Services\DeviceManager;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\Province;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * People: search, person page, roles, devices, suspension.
 */
final class PeopleController
{
    use Concerns;

    /** Roles only a super admin may give or take away. */
    public const NATIONAL_ROLES = ['super_admin', 'operations_admin', 'support_agent', 'content_reviewer', 'commercial_admin', 'finance_admin'];

    public function index(Request $request, PeopleDirectory $directory, RoleRegistry $roles): Response
    {
        $filters = $request->only(['q', 'hub', 'province', 'role', 'status']);
        $people = $directory->search($filters);

        return Inertia::render('Admin/People/Index', [
            'filters' => $filters,
            'people' => $people->through(static fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->fullName(),
                'phone' => SaFormat::maskedPhone($u->phone),
                'hub' => $u->homeHub?->name,
                'status' => $u->status,
                'joined' => $u->created_at?->toIso8601String(),
            ]),
            'options' => [
                'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
                'provinces' => Province::query()->orderBy('name')->get(['id', 'name']),
                'roles' => collect($roles->all())->map(static fn ($r): array => ['key' => $r->key, 'label' => $r->label])->values(),
                'statuses' => [User::STATUS_ACTIVE, User::STATUS_PENDING_GUARDIAN, User::STATUS_SUSPENDED, User::STATUS_DELETION_REQUESTED],
            ],
        ]);
    }

    public function show(Request $request, User $person, RoleRegistry $roles, DocumentVault $vault, AuditLogger $audit): Response
    {
        $audit->record('admin.person_viewed', $person, actor: $this->actor($request));

        return Inertia::render('Admin/People/Show', [
            'person' => [
                'id' => $person->id,
                'name' => $person->fullName(),
                'preferredName' => $person->preferred_name,
                'phone' => SaFormat::phone($person->phone),
                'email' => $person->email,
                'dateOfBirth' => $person->date_of_birth->toDateString(),
                'ageBand' => $person->age_band,
                'status' => $person->status,
                'hub' => $person->homeHub?->name,
                'place' => $person->place_name,
                'joined' => $person->created_at?->toIso8601String(),
                'lastLogin' => $person->last_login_at?->toIso8601String(),
                'staff' => $person->two_factor_required,
            ],
            'roles' => $person->roleAssignments()->get()->map(static fn (RoleAssignment $a): array => [
                'id' => $a->id,
                'role' => $a->role,
                'label' => $roles->find($a->role)->label ?? $a->role,
                'scopeType' => $a->scope_type,
                'scopeId' => $a->scope_id,
                'where' => (new Scope($a->scope_type, $a->scope_id))->describe(),
                'national' => in_array($a->role, self::NATIONAL_ROLES, true),
            ]),
            'devices' => $person->devices()->whereNull('revoked_at')->count(),
            'consents' => Consent::query()->where('user_id', $person->id)->latest('created_at')->limit(30)->get()
                ->map(static fn (Consent $c): array => ['purpose' => $c->purpose, 'granted' => $c->granted, 'channel' => $c->channel, 'at' => $c->created_at->toIso8601String()]),
            'documents' => Document::query()->where('user_id', $person->id)->latest()->get()->map(static fn (Document $d): array => [
                'id' => $d->id, 'type' => $d->type, 'status' => $d->status, 'url' => $d->canBeOpened() ? $vault->temporaryUrl($d, inline: true) : null,
            ]),
            'audit' => AuditLog::query()->where('user_id', $person->id)->latest('created_at')->limit(30)->get()
                ->map(static fn (AuditLog $l): array => ['event' => $l->event, 'outcome' => $l->outcome, 'at' => $l->created_at->toIso8601String(), 'byStaff' => $l->actor_id !== null]),
            'roleOptions' => collect($roles->all())->map(static fn ($r): array => ['key' => $r->key, 'label' => $r->label, 'scope' => $r->scope, 'national' => in_array($r->key, self::NATIONAL_ROLES, true)])->values(),
            'scopeOptions' => [
                'hub' => Hub::query()->orderBy('name')->get(['id', 'name']),
                'organisation' => Organisation::query()->orderBy('name')->get(['id', 'name']),
                'municipality' => Municipality::query()->where('category', '!=', 'district')->orderBy('name')->get(['id', 'name']),
                'province' => Province::query()->orderBy('name')->get(['id', 'name']),
            ],
            'can' => [
                'assignRoles' => $this->can($request, 'admin.roles.assign'),
                'assignNational' => $this->can($request, 'admin.roles.assign_national'),
                'suspend' => $this->can($request, 'admin.people.suspend'),
            ],
        ]);
    }

    public function assignRole(Request $request, User $person, RoleAssignments $assignments, RoleRegistry $roles): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(array_keys($roles->all()))],
            'scope_id' => ['nullable', 'string', 'max:26'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->guardNational($request, $person, $validated['role']);
        $role = $roles->get($validated['role']);

        try {
            $scope = new Scope($role->scope, in_array($role->scope, ['self', 'national'], true) ? null : ($validated['scope_id'] ?? ''));
            $assignments->assign($person, $role->key, $scope, $this->actor($request), reason: $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        return back()->with('status', __('admin.roles.assigned'));
    }

    public function revokeRole(Request $request, User $person, RoleAssignment $assignment, RoleAssignments $assignments): RedirectResponse
    {
        abort_unless($assignment->user_id === $person->id, 404);
        $reason = $this->validateReason($request)['reason'];
        $this->guardNational($request, $person, $assignment->role);

        $assignments->revoke($person, $assignment->role, new Scope($assignment->scope_type, $assignment->scope_id), $this->actor($request), $reason);

        return back()->with('status', __('admin.roles.revoked'));
    }

    public function suspend(Request $request, User $person, AccountModeration $moderation): RedirectResponse
    {
        $reason = $this->validateReason($request)['reason'];
        abort_if($person->id === $this->actor($request)->id, 422, 'You cannot suspend your own account.');

        $moderation->suspend($person, $this->actor($request), $reason);

        return back()->with('status', __('admin.people.suspended'));
    }

    public function reactivate(Request $request, User $person, AccountModeration $moderation): RedirectResponse
    {
        $moderation->reactivate($person, $this->actor($request), $this->validateReason($request)['reason']);

        return back()->with('status', __('admin.people.reactivated'));
    }

    public function signOutEverywhere(Request $request, User $person, DeviceManager $devices, AuditLogger $audit): RedirectResponse
    {
        $count = $devices->revokeAllExcept($person, null);
        $audit->record('account.signed_out_everywhere', $person, meta: ['devices' => $count], actor: $this->actor($request));

        return back()->with('status', __('admin.people.signed_out', ['count' => $count]));
    }

    /** Only super admins give or take away national roles - and nobody changes their own. */
    private function guardNational(Request $request, User $person, string $role): void
    {
        if (! in_array($role, self::NATIONAL_ROLES, true)) {
            return;
        }

        abort_unless($this->can($request, 'admin.roles.assign_national'), 403);
        abort_if($person->id === $this->actor($request)->id, 403, 'You cannot change your own national role.');
    }
}
