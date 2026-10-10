<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use Illuminate\Http\UploadedFile;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\OrganisationRegistration;
use Modules\Work\Events\EmployerRegistered;

/**
 * Employer registration (S10), built on the shared core organisation registration. Businesses with
 * a CIPC registration upload their certificate; community employers are checked through ID, proof
 * of address and a hub visit. Either way the KasiHub team verifies before any listing goes live.
 */
final readonly class EmployerRegistration
{
    public function __construct(private OrganisationRegistration $registration) {}

    /** @param array<string, mixed> $data */
    public function register(User $user, array $data, ?UploadedFile $certificate, ?User $by = null): Organisation
    {
        $organisation = $this->registration->register($user, 'employer', 'employer_admin', $data, $certificate, $by);
        event(new EmployerRegistered($user, $by?->id, ['organisation' => $organisation->id, 'community' => (bool) ($data['community'] ?? false)]));

        return $organisation;
    }

    public function addRecruiter(Organisation $employer, User $person, User $by): void
    {
        $this->registration->addMember($employer, $person, 'recruiter', $by, 'Recruiter');
    }

    public function removeRecruiter(Organisation $employer, User $person, User $by): void
    {
        $this->registration->removeMember($employer, $person, 'recruiter', $by);
    }

    public static function validCipcNumber(string $number): bool
    {
        return OrganisationRegistration::validCipcNumber($number);
    }
}
