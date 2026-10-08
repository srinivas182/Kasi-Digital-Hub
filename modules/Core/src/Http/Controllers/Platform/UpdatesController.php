<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Platform;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\Update;

/**
 * The "what changed and why" feed (also where in-app notifications land).
 */
final class UpdatesController
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        $updates = Update::query()->where('user_id', $user->id)->latest('created_at')->latest('id')->paginate(20);

        return Inertia::render('Hub/Updates', [
            'updates' => $updates->through(static fn (Update $u): array => [
                'id' => $u->id,
                'module' => $u->module,
                'title' => $u->title,
                'body' => $u->body,
                'cause' => $u->cause,
                'url' => $u->url,
                'read' => $u->read_at !== null,
                'createdAt' => $u->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function open(Request $request, Update $update): RedirectResponse
    {
        abort_unless($update->user_id === $this->user($request)->id, 404);
        $update->update(['read_at' => $update->read_at ?? now()]);

        return redirect($update->url ?? route('hub.updates'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        Update::query()->where('user_id', $this->user($request)->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
