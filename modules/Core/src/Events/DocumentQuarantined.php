<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Modules\Core\Documents\Models\Document;

final class DocumentQuarantined extends PlatformEvent
{
    public const NAME = 'core.document.quarantined';

    public const DESCRIPTION = 'A virus scan flagged an uploaded document; it is quarantined and cannot be opened.';

    public function __construct(public readonly Document $document, public readonly ?string $by = null) {}

    public function userId(): string
    {
        return $this->document->user_id;
    }

    public function actorId(): ?string
    {
        return $this->by;
    }

    public function subject(): array
    {
        return ['document', $this->document->id];
    }

    public function payload(): array
    {
        return ['type' => $this->document->type];
    }
}
