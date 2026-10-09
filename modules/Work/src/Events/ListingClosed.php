<?php

declare(strict_types=1);

namespace Modules\Work\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\Work\Models\JobListing;

final class ListingClosed extends PlatformEvent
{
    public const NAME = 'work.listing.closed';

    public const DESCRIPTION = 'A job listing closed (filled, closed by the employer, expired or taken down).';

    public function __construct(public readonly JobListing $listing, public readonly ?string $by = null) {}

    public function userId(): ?string
    {
        return $this->listing->created_by;
    }

    public function actorId(): ?string
    {
        return $this->by;
    }

    public function subject(): array
    {
        return ['job_listing', $this->listing->id];
    }

    public function payload(): array
    {
        return ['organisation' => $this->listing->organisation_id, 'type' => $this->listing->type, 'status' => $this->listing->status, 'occupation' => $this->listing->occupation_id];
    }
}
