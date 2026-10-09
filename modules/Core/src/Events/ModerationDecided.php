<?php

declare(strict_types=1);

namespace Modules\Core\Events;

/**
 * A reviewer decided on flagged content. Portals listen for their own subject types
 * (e.g. KasiWork publishes or takes down a job listing).
 */
final class ModerationDecided extends PlatformEvent
{
    public const NAME = 'core.moderation.decided';

    public const DESCRIPTION = 'A reviewer approved, rejected or escalated flagged content.';

    public function __construct(
        public readonly string $subjectType,
        public readonly string $subjectId,
        public readonly string $decision,
        public readonly string $reason,
        public readonly string $by,
    ) {}

    public function actorId(): string
    {
        return $this->by;
    }

    public function subject(): array
    {
        return [$this->subjectType, $this->subjectId];
    }

    public function payload(): array
    {
        return ['decision' => $this->decision];
    }
}
