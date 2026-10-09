<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Matching numbers and a basic fairness check for the KasiHub team: are people with only informal
 * experience, or without matric, matched and invited as often as others? No protected attributes.
 */
final class InsightsController
{
    public function __invoke(Request $request): Response
    {
        $people = DB::table('work_profiles')->pluck('user_id');
        $formal = DB::table('work_experiences')->whereIn('kind', ['job', 'learnership'])->distinct()->pluck('user_id')->flip();
        $anyExp = DB::table('work_experiences')->distinct()->pluck('user_id')->flip();
        $matric = DB::table('work_education')->whereIn('kind', ['matric', 'certificate', 'diploma', 'degree'])->distinct()->pluck('user_id')->flip();
        $strong = DB::table('work_matches')->where('score', '>=', 70)->where('has_gaps', false)->select('user_id', DB::raw('count(*) as c'))->groupBy('user_id')->pluck('c', 'user_id');
        $invited = DB::table('work_invitations')->distinct()->pluck('user_id')->flip();

        $group = static fn (string $id): string => isset($formal[$id]) ? 'formal' : (isset($anyExp[$id]) ? 'informal_only' : 'no_experience');
        $rows = [];
        foreach ($people as $id) {
            $id = (string) $id;
            foreach (['experience' => $group($id), 'education' => isset($matric[$id]) ? 'matric_or_more' : 'below_matric'] as $dimension => $value) {
                $key = $dimension.'|'.$value;
                $rows[$key] ??= ['dimension' => $dimension, 'group' => $value, 'people' => 0, 'withStrongMatch' => 0, 'invited' => 0];
                $rows[$key]['people']++;
                $rows[$key]['withStrongMatch'] += isset($strong[$id]) ? 1 : 0;
                $rows[$key]['invited'] += isset($invited[$id]) ? 1 : 0;
            }
        }

        return Inertia::render('Work/Insights', [
            'totals' => [
                'profiles' => $people->count(),
                'visible' => DB::table('consents')->where('purpose', 'job_matching')->where('granted', true)->distinct()->count('user_id'),
                'matches' => DB::table('work_matches')->count(),
                'strongMatches' => DB::table('work_matches')->where('score', '>=', 70)->count(),
                'invitations' => DB::table('work_invitations')->count(),
                'accepted' => DB::table('work_invitations')->where('status', 'accepted')->count(),
            ],
            'fairness' => array_values($rows),
            'byHub' => DB::table('work_matches')->join('users', 'users.id', '=', 'work_matches.user_id')->join('hubs', 'hubs.id', '=', 'users.home_hub_id')
                ->where('work_matches.score', '>=', 70)->groupBy('hubs.name')->selectRaw('hubs.name as hub, count(*) as matches, count(distinct work_matches.user_id) as people')
                ->orderByDesc('matches')->get(),
        ]);
    }
}
