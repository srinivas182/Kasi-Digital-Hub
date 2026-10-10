<?php

declare(strict_types=1);

namespace Modules\Start\Services;

use Illuminate\Http\Request;
use Modules\Core\Assist\AssistedSession;
use Modules\Core\Identity\Models\User;

/**
 * The person KasiStart works for: the signed-in adult, or - during a hub help session - the person
 * being helped, with the facilitator recorded as the actor. KasiStart is for adults (18+).
 */
final readonly class StartSubject
{
    public function __construct(private AssistedSession $assist) {}

    /** @return array{0: User, 1: User|null} */
    public function resolve(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $helped = $this->assist->person($user);
        $subject = $helped ?? $user;
        abort_if($subject->isMinor(), 403);

        return [$subject, $helped !== null ? $user : null];
    }
}
