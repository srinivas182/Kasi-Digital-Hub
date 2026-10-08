<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Base class for every cross-portal event (the cause-and-effect catalogue).
 *
 * - Dispatched only after the database transaction commits, so no event is ever
 *   published for a change that was rolled back.
 * - Recorded in the platform event log (platform_events) by EventRecorder, which feeds
 *   the updates feed and, from Sprint 20, the impact dashboards.
 * - Portals react to each other's events through listeners - never by calling each other's code.
 *
 * Each subclass declares NAME (e.g. "core.document.verified") and DESCRIPTION, and lists
 * NAME under "events.publishes" in its module.json.
 */
abstract class PlatformEvent implements IsPlatformEvent, ShouldDispatchAfterCommit
{
    public const NAME = '';

    public const DESCRIPTION = '';

    /** Person the event is about (if any). */
    public function userId(): ?string
    {
        return null;
    }

    /** Who caused it, when different from the person (e.g. a facilitator or admin). */
    public function actorId(): ?string
    {
        return null;
    }

    /** @return array{0: string, 1: string}|null [type, id] of the main thing the event is about */
    public function subject(): ?array
    {
        return null;
    }

    public function hubId(): ?string
    {
        return null;
    }

    /**
     * Small, non-sensitive details to keep with the event (never PINs, codes, ID numbers or document contents).
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [];
    }
}
