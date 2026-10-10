<?php

declare(strict_types=1);

namespace Modules\Core\Home;

use Modules\Core\Identity\Models\User;

/**
 * Extension point: skills a person is missing for things they want (e.g. KasiWork: must-have skills
 * of jobs that match them). KasiLearn uses it to suggest courses. Tag implementations with TAG.
 */
interface SkillGapSource
{
    public const TAG = 'kasi.skill_gaps';

    /** @return list<string> lower-case skill names, most useful first */
    public function gaps(User $user): array;
}
