<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use Illuminate\Http\Request;
use Modules\Core\Assist\AssistedSession;
use Modules\Core\Identity\Models\User;

/**
 * Whose job profile a request works on: the signed-in person, or - while a facilitator is
 * helping someone at the hub (AssistedSession) - that person, with the facilitator as actor.
 * KasiWork is for adults only (age policy).
 */
final readonly class WorkSubject
{
    public function __construct(private AssistedSession $assist) {}

    /** @return array{0: User, 1: User|null} [subject, actor or null when acting for themselves] */
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
