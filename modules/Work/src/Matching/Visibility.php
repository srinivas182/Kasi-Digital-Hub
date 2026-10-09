<?php

declare(strict_types=1);

namespace Modules\Work\Matching;

use Illuminate\Support\Facades\DB;

/**
 * Who employers may see: people whose latest "job matching" consent is on and who have not hidden
 * their profile from that employer.
 */
final class Visibility
{
    /**
     * @param  list<string>  $userIds
     * @return list<string> the ones with job matching switched on
     */
    public function consenting(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $latest = [];
        foreach (DB::table('consents')->whereIn('user_id', $userIds)->where('purpose', 'job_matching')->orderBy('created_at')->orderBy('id')->get(['user_id', 'granted']) as $row) {
            $latest[(string) $row->user_id] = (bool) $row->granted;
        }

        return array_keys(array_filter($latest));
    }

    public function canSee(string $organisationId, string $userId): bool
    {
        return $this->consenting([$userId]) !== []
            && ! DB::table('work_hidden_employers')->where('user_id', $userId)->where('organisation_id', $organisationId)->exists();
    }

    /** @return list<string> */
    public function hiddenFrom(string $organisationId): array
    {
        return array_values(array_map('strval', DB::table('work_hidden_employers')->where('organisation_id', $organisationId)->pluck('user_id')->all()));
    }
}
