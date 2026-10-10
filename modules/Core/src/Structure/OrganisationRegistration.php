<?php

declare(strict_types=1);

namespace Modules\Core\Structure;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * Self-service organisation registration shared by the portals (employers, training providers ...):
 * creates the organisation waiting for verification, stores the registration certificate in the
 * person's document vault, and makes the person its admin. Also manages team members.
 */
final readonly class OrganisationRegistration
{
    public function __construct(private DocumentVault $vault, private RoleAssignments $roles, private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function register(User $user, string $type, string $adminRole, array $data, ?UploadedFile $certificate, ?User $by = null): Organisation
    {
        $community = (bool) ($data['community'] ?? false);

        $organisation = DB::transaction(function () use ($user, $type, $adminRole, $data, $certificate, $community, $by): Organisation {
            $document = $certificate !== null ? $this->vault->store($user, $certificate, 'cipc_certificate', uploadedBy: $by) : null;

            $organisation = Organisation::query()->create([
                'type' => $type, 'name' => trim((string) $data['name']), 'trading_name' => $data['trading_name'] ?? null,
                'registration_number' => $community ? null : ($data['registration_number'] ?? null), 'community' => $community,
                'sector' => $data['sector'] ?? null, 'size_band' => $data['size_band'] ?? null, 'municipality_id' => $data['municipality_id'],
                'address' => $data['address'] ?? null, 'contact_phone' => $data['contact_phone'] ?? $user->phone,
                'contact_email' => $data['contact_email'] ?? null, 'description' => $data['description'] ?? null,
                'registration_document_id' => $document?->id, 'verification_status' => 'pending',
            ]);
            $organisation->members()->attach($user->id, ['title' => $data['title'] ?? null]);
            $this->roles->assign($user, $adminRole, Scope::organisation($organisation), $by);

            return $organisation;
        });

        $this->audit->record("{$type}.registered", $user, meta: ['organisation' => $organisation->id, 'community' => $community], actor: $by);

        return $organisation;
    }

    public function addMember(Organisation $organisation, User $person, string $role, User $by, string $title): void
    {
        if (RoleAssignment::query()->where('user_id', $person->id)->where('role', $role)->where('scope_type', 'organisation')->where('scope_id', $organisation->id)->exists()) {
            throw new InvalidArgumentException(__('work.team.already'));
        }

        $organisation->members()->syncWithoutDetaching([$person->id => ['title' => $title]]);
        $this->roles->assign($person, $role, Scope::organisation($organisation), $by);
    }

    public function removeMember(Organisation $organisation, User $person, string $role, User $by): void
    {
        $this->roles->revoke($person, $role, Scope::organisation($organisation), $by);
        if (! RoleAssignment::query()->where('user_id', $person->id)->where('scope_type', 'organisation')->where('scope_id', $organisation->id)->exists()) {
            $organisation->members()->detach($person->id);
        }
    }

    /** CIPC numbers look like 2021/123456/07. */
    public static function validCipcNumber(string $number): bool
    {
        return preg_match('#^(19|20)\d{2}/\d{6}/\d{2}$#', trim($number)) === 1;
    }
}
