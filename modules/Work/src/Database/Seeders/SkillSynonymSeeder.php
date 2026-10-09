<?php

declare(strict_types=1);

namespace Modules\Work\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Common ways people and employers name the same skill. Each group maps to the first phrase.
 * Kept small and curated; administrators can extend it.
 */
final class SkillSynonymSeeder extends Seeder
{
    public const GROUPS = [
        ['cash handling', 'till operation', 'cashier', 'handling cash', 'cash register', 'till', 'point of sale', 'pos'],
        ['customer service', 'serving customers', 'customer care', 'helping customers', 'client service', 'customer relations'],
        ['stock counting', 'stock taking', 'stocktake', 'inventory', 'counting stock', 'stock control'],
        ['shelf packing', 'packing shelves', 'merchandising', 'shelf stacking', 'packing stock', 'merchandiser'],
        ['cleaning', 'housekeeping', 'cleaner', 'janitorial', 'domestic cleaning'],
        ['cooking', 'food preparation', 'cook', 'kitchen work', 'meal preparation', 'catering'],
        ['general labour', 'general worker', 'manual labour', 'labourer', 'general work'],
        ['bricklaying', 'brick laying', 'building', 'bricklayer'],
        ['driving', 'driver', 'deliveries', 'delivery driving'],
        ['microsoft word', 'ms word', 'word processing', 'typing documents'],
        ['microsoft excel', 'ms excel', 'excel', 'spreadsheets'],
        ['data capturing', 'data capture', 'data entry', 'capturing data'],
        ['child care', 'childcare', 'looking after children', 'babysitting', 'nanny'],
        ['elderly care', 'caring for the elderly', 'home-based care', 'caregiving', 'care work'],
        ['security', 'security guard', 'guarding', 'access control'],
        ['gardening', 'garden work', 'landscaping', 'garden maintenance'],
        ['farm work', 'farming', 'harvesting', 'fruit picking', 'agriculture'],
        ['sales', 'selling', 'selling at a stall', 'street vending', 'hawking'],
        ['call centre', 'call center', 'telephone sales', 'answering phones', 'telesales'],
        ['teamwork', 'team player', 'working in a team'],
        ['time keeping', 'punctuality', 'arriving on time'],
        ['forklift', 'forklift driving', 'forklift operator'],
        ['painting', 'house painting', 'painter'],
        ['hairdressing', 'braiding', 'hair styling', 'barbering', 'hair'],
        ['email and internet', 'internet', 'email', 'computer literacy', 'basic computer skills'],
        ['bookkeeping basics', 'bookkeeping', 'record keeping', 'keeping records'],
    ];

    public function run(): void
    {
        foreach (self::GROUPS as $group) {
            foreach ($group as $phrase) {
                DB::table('work_skill_synonyms')->insertOrIgnore(['phrase' => $phrase, 'canonical' => $group[0]]);
            }
        }
    }
}
