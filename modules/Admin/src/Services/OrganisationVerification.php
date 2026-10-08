<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Modules\Admin\Events\OrganisationRejected;
use Modules\Admin\Events\OrganisationVerified;
use Modules\Admin\Notifications\Messages\OrganisationRejectedNotification;
use Modules\Admin\Notifications\Messages\OrganisationVerifiedNotification;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\Organisation;

/**
 * Verifying organisations (employers, providers, partners, funders) before they can act on the platform.
 * Members are told the outcome.
 */
final readonly class OrganisationVerification
{
    public function __construct(private AuditLogger $audit, private Notifier $notifier) {}

    public function verify(Organisation $organisation, User $by): void
    {
        $organisation->forceFill(['verification_status' => 'verified', 'verified_at' => now()])->save();
        $this->audit->record('organisation.verified', meta: ['organisation' => $organisation->id], actor: $by);
        event(new OrganisationVerified($organisation, $by->id));

        $organisation->members()->get()->each(fn (User $member) => $this->notifier->send($member, new OrganisationVerifiedNotification($organisation->name)));
    }

    public function reject(Organisation $organisation, string $reason, User $by): void
    {
        $organisation->forceFill(['verification_status' => 'rejected', 'verified_at' => null])->save();
        $this->audit->record('organisation.rejected', meta: ['organisation' => $organisation->id, 'reason' => $reason], actor: $by);
        event(new OrganisationRejected($organisation, $by->id, $reason));

        $organisation->members()->get()->each(fn (User $member) => $this->notifier->send($member, new OrganisationRejectedNotification($organisation->name, $reason)));
    }
}
