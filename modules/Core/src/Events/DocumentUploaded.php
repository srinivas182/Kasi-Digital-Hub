<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Modules\Core\Documents\Models\Document;

final class DocumentUploaded extends PlatformEvent
{
    public const NAME = 'core.document.uploaded';

    public const DESCRIPTION = "A document was uploaded to a person's vault (before virus scanning).";

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
