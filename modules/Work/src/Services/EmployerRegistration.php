<?php

declare(strict_types=1);

namespace Modules\Work\Services;

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
use Modules\Work\Events\EmployerRegistered;

/**
 * Self-service employer registration. Businesses with a CIPC registration upload their certificate;
 * community employers (no registration) are checked through ID, proof of address and a hub visit.
 * Either way the KasiHub team verifies before any listing can be published.
 */
final readonly class EmployerRegistration
{
    public function __construct(
        private DocumentVault $vault,
        private RoleAssignments $roles,
        private AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function register(User $user, array $data, ?UploadedFile $certificate, ?User $by = null): Organisation
    {
        $community = (bool) ($data['community'] ?? false);

        $organisation = DB::transaction(function () use ($user, $data, $certificate, $community, $by): Organisation {
            $document = $certificate !== null ? $this->vault->store($user, $certificate, 'cipc_certificate', uploadedBy: $by) : null;

            $organisation = Organisation::query()->create([
                'type' => 'employer', 'name' => trim((string) $data['name']), 'trading_name' => $data['trading_name'] ?? null,
                'registration_number' => $community ? null : $data['registration_number'], 'community' => $community,
                'sector' => $data['sector'], 'size_band' => $data['size_band'], 'municipality_id' => $data['municipality_id'],
                'address' => $data['address'] ?? null, 'contact_phone' => $data['contact_phone'] ?? $user->phone,
                'contact_email' => $data['contact_email'] ?? null, 'description' => $data['description'] ?? null,
                'registration_document_id' => $document?->id, 'verification_status' => 'pending',
            ]);
            $organisation->members()->attach($user->id, ['title' => $data['title'] ?? null]);
            $this->roles->assign($user, 'employer_admin', Scope::organisation($organisation), $by);

            return $organisation;
        });

        $this->audit->record('work.employer_registered', $user, meta: ['organisation' => $organisation->id, 'community' => $community], actor: $by);
        event(new EmployerRegistered($user, $by?->id, ['organisation' => $organisation->id, 'community' => $community]));

        return $organisation;
    }

    public function addRecruiter(Organisation $employer, User $person, User $by): void
    {
        if (RoleAssignment::query()->where('user_id', $person->id)->where('scope_type', 'organisation')->where('scope_id', $employer->id)->exists()) {
            throw new InvalidArgumentException(__('work.team.already'));
        }

        $employer->members()->syncWithoutDetaching([$person->id => ['title' => 'Recruiter']]);
        $this->roles->assign($person, 'recruiter', Scope::organisation($employer), $by);
    }

    public function removeRecruiter(Organisation $employer, User $person, User $by): void
    {
        $this->roles->revoke($person, 'recruiter', Scope::organisation($employer), $by);
        $employer->members()->detach($person->id);
    }

    /** CIPC numbers look like 2021/123456/07. */
    public static function validCipcNumber(string $number): bool
    {
        return preg_match('#^(19|20)\d{2}/\d{6}/\d{2}$#', trim($number)) === 1;
    }
}
