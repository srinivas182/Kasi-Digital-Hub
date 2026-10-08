<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Documents\Models\Document;

/**
 * Virus-scans a new upload. Until this succeeds the document cannot be opened.
 */
final class ScanDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly string $documentId)
    {
        $this->onQueue('documents');
    }

    public function handle(DocumentVault $vault): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document !== null && $document->status === Document::PENDING_SCAN) {
            $vault->scan($document);
        }
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }
}
