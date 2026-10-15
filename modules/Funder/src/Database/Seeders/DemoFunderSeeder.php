<?php

declare(strict_types=1);

namespace Modules\Funder\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Structure\Models\Organisation;
use Modules\Funder\Models\Programme;

/** Demo: the sample Limpopo funder's youth programme with targets (fictional). */
final class DemoFunderSeeder extends Seeder
{
    public function run(): void
    {
        $funder = Organisation::query()->where('type', 'funder')->where('name', 'like', 'Limpopo Youth Skills Programme%')->first();
        $limpopo = DB::table('provinces')->where('code', 'LP')->value('id') ?? DB::table('provinces')->where('name', 'Limpopo')->value('id');
        if ($funder === null || Programme::query()->exists()) {
            return;
        }
        $p = Programme::query()->create(['organisation_id' => $funder->id, 'name' => 'Limpopo Youth Pathways 2026 (demo)', 'summary' => 'Work, learning and enterprise support for young people in Limpopo hubs.',
            'scope' => ['province_ids' => $limpopo !== null ? [(int) $limpopo] : []], 'starts_on' => now()->subMonths(2)->startOfMonth()->toDateString(),
            'ends_on' => now()->addMonths(10)->endOfMonth()->toDateString(), 'age_min' => 16, 'age_max' => 35, 'status' => 'active']);
        foreach (['reach.registered' => 2000, 'work.profiles' => 800, 'work.hires' => 150, 'learn.completions' => 600, 'start.formalised' => 60, 'connect.mentorships' => 100] as $indicator => $target) {
            DB::table('fund_targets')->insert(['programme_id' => $p->id, 'indicator' => $indicator, 'target' => $target]);
        }
    }
}
