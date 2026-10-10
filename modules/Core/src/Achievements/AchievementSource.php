<?php

declare(strict_types=1);

namespace Modules\Core\Achievements;

use Modules\Core\Identity\Models\User;

/**
 * Extension point: verified achievements a person can show, e.g. KasiLearn certificates on the
 * KasiWork CV. Tag implementations with TAG.
 */
interface AchievementSource
{
    public const TAG = 'kasi.achievements';

    /** @return list<array{title: string, issuer: string, date: string, code: string|null}> */
    public function achievements(User $user): array;
}
