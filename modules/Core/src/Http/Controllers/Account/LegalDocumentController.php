<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Account;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\ConsentDocument;

/**
 * Public view of the current terms of use and privacy notice.
 */
final class LegalDocumentController
{
    public function __invoke(string $key): Response
    {
        $document = ConsentDocument::query()
            ->where('key', $key)
            ->where('published_at', '<=', now())
            ->orderByDesc('version')
            ->firstOrFail();

        return Inertia::render('Core/Legal', [
            'title' => $document->title,
            'version' => $document->version,
            'publishedAt' => $document->published_at->toIso8601String(),
            'body' => $document->body,
        ]);
    }
}
