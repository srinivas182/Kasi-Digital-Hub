<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Work\Events\ProfileCompleted;
use Modules\Work\Models\WorkEducation;
use Modules\Work\Models\WorkExperience;
use Modules\Work\Models\WorkProfile;

/**
 * The job seeker profile: creates it on first save (and gives the person the job seeker role),
 * keeps the completeness score, and announces when the essentials are done.
 */
final readonly class SeekerProfile
{
    public function __construct(private RoleAssignments $roles, private AuditLogger $audit) {}

    public function for(User $user): WorkProfile
    {
        return WorkProfile::query()->firstOrNew(['user_id' => $user->id]);
    }

    /** @param array<string, mixed> $attributes */
    public function save(User $user, array $attributes, ?User $by = null): WorkProfile
    {
        $profile = $this->for($user);
        $profile->fill($attributes)->save();

        if (! RoleAssignment::query()->where('user_id', $user->id)->where('role', 'job_seeker')->exists()) {
            $this->roles->assign($user, 'job_seeker', Scope::self(), $by);
        }

        $this->audit->record('work.profile_saved', $user, meta: ['fields' => array_keys($attributes)], actor: $by);

        return $this->refresh($user, $by);
    }

    /** Recalculate completeness after any change; fire the "completed" event once. */
    public function refresh(User $user, ?User $by = null): WorkProfile
    {
        $profile = $this->for($user);
        if (! $profile->exists) {
            $profile->save();
        }

        $parts = $this->parts($user, $profile);
        $profile->completeness = (int) round(100 * count(array_filter($parts)) / count($parts));

        $essentials = $parts['about'] && $parts['experience_or_education'] && $parts['skills'];
        if ($essentials && $profile->completed_at === null) {
            $profile->completed_at = CarbonImmutable::now();
            $profile->save();
            event(new ProfileCompleted($user, $by?->id));
        } else {
            $profile->save();
        }

        return $profile;
    }

    /** @return array<string, bool> */
    public function parts(User $user, WorkProfile $profile): array
    {
        $experience = WorkExperience::query()->where('user_id', $user->id)->exists();
        $education = WorkEducation::query()->where('user_id', $user->id)->exists();

        return [
            'about' => filled($profile->headline) && filled($profile->summary),
            'experience_or_education' => $experience || $education,
            'experience' => $experience,
            'education' => $education,
            'skills' => DB::table('work_skills')->where('user_id', $user->id)->count() >= 3,
            'languages' => DB::table('work_languages')->where('user_id', $user->id)->exists(),
            'looking_for' => ! empty($profile->work_types),
        ];
    }
}
