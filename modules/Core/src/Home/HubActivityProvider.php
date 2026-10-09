<?php

declare(strict_types=1);

namespace Modules\Core\Home;

use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;

/**
 * Portal SDK extension point for things happening at hubs (events, classes, job days).
 * The public website lists a hub's public items; the hub home lists a person's own.
 * Tag implementations with HubActivity::TAG.
 */
interface HubActivityProvider
{
    /** @return list<array{id: string, title: string, type: string, startsAt: string, endsAt: string, room: string|null, href: string}> */
    public function publicItems(Hub $hub): array;

    /** @return list<array{id: string, title: string, type: string, startsAt: string, endsAt: string, room: string|null, href: string, hub: string, status: string}> */
    public function personalItems(User $user): array;
}
