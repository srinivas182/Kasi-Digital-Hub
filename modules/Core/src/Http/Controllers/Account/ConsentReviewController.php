<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\ConsentDocument;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;

/**
 * Shown when the terms of use or privacy notice change: the person must accept the
 * new versions before continuing.
 */
final class ConsentReviewController
{
    public function show(ConsentService $consents): Response
    {
        return Inertia::render('Core/Account/ConsentReview', [
            'documents' => $consents->currentDocuments()->map(static fn (ConsentDocument $d): array => [
                'key' => $d->key, 'version' => $d->version, 'title' => $d->title, 'summary' => $d->summary,
            ])->values(),
        ]);
    }

    public function store(Request $request, ConsentService $consents): RedirectResponse
    {
        $request->validate(['accept_terms' => ['accepted']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $consents->record($user, ['platform' => true]);

        return redirect()->intended(route('hub.home'));
    }
}
