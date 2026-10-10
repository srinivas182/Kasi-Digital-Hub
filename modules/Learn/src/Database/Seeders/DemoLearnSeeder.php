<?php

declare(strict_types=1);

namespace Modules\Learn\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Organisation;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\CourseModule;
use Modules\Learn\Models\Lesson;
use Modules\Learn\Services\CourseWorkflow;

/**
 * Three short demo courses from HBM EduTech (clearly marked demo) for the catalogue, and a draft.
 * Real courses replace these.
 */
final class DemoLearnSeeder extends Seeder
{
    public function run(CourseWorkflow $workflow): void
    {
        $hbm = Organisation::query()->where('type', 'training_provider')->first();
        $herman = User::query()->where('phone', '+27720000012')->first();
        if ($hbm === null || $herman === null || Course::query()->exists()) {
            return;
        }
        $hbm->forceFill(['verification_status' => 'verified', 'verified_at' => now(), 'description' => 'HBM EduTech builds practical digital and work-readiness training. Demo courses - not real.',
            'verification_checklist' => ['cipc_found' => true, 'cipc_active' => true, 'person_linked' => true, 'phone_answered' => true]])->save();

        $courses = [
            ['Digital skills basics (demo)', 'digital_skills', 16, 'Use a smartphone and a computer with confidence: email, safe browsing, documents and online forms.',
                ['You will be able to send an email with an attachment', 'You will be able to spot a scam message', 'You will be able to fill in an online form'],
                ['Getting started' => ['Your phone is a tool', 'Email in five steps'], 'Staying safe online' => ['Spotting scams', 'Strong PINs and passwords']]],
            ['Customer service essentials (demo)', 'customer_service', 16, 'Serve customers well in a shop, restaurant or office: greeting, listening, handling cash and complaints.',
                ['You will be able to greet and help a customer politely', 'You will be able to handle a complaint calmly', 'You will be able to count change correctly'],
                ['Serving customers' => ['First impressions', 'Listening well'], 'Money and complaints' => ['Cash handling and change', 'When a customer is unhappy']]],
            ['Start a small business (demo)', 'business', 18, 'Turn an idea into a small business: costs, prices, records and registering with CIPC.',
                ['You will be able to work out the price of a product', 'You will be able to keep a simple cash book', 'You will be able to explain how to register a business'],
                ['Your idea' => ['Is there a market?', 'Costs and prices'], 'Running it' => ['Keeping records', 'Registering your business']]],
        ];

        foreach ($courses as [$title, $topic, $age, $summary, $outcomes, $modules]) {
            $course = Course::query()->create(['organisation_id' => $hbm->id, 'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)), 'title' => $title,
                'summary' => $summary, 'outcomes' => $outcomes, 'topic' => $topic, 'level' => 'beginner', 'hours' => 3, 'language' => 'English', 'min_age' => $age,
                'delivery' => 'self_paced', 'licence' => 'cc_by', 'attribution' => 'HBM EduTech (demo)', 'created_by' => $herman->id]);
            $mp = 0;
            foreach ($modules as $moduleTitle => $lessons) {
                $module = CourseModule::query()->create(['course_id' => $course->id, 'title' => $moduleTitle, 'position' => $mp++]);
                foreach ($lessons as $lp => $lessonTitle) {
                    Lesson::query()->create(['course_id' => $course->id, 'module_id' => $module->id, 'title' => $lessonTitle, 'kind' => 'text', 'position' => $lp,
                        'minutes' => 10, 'preview' => $mp === 1 && $lp === 0, 'content' => self::doc($lessonTitle, $title)]);
                }
            }
            $workflow->publish($course, $herman);
        }

        $draft = Course::query()->create(['organisation_id' => $hbm->id, 'slug' => 'cv-and-interview-skills-demo', 'title' => 'CV and interview skills (demo, draft)',
            'summary' => 'Prepare a strong CV and practise interviews.', 'topic' => 'work_readiness', 'min_age' => 16, 'created_by' => $herman->id]);
        CourseModule::query()->create(['course_id' => $draft->id, 'title' => 'Module 1', 'position' => 0]);
    }

    /** @return array<string, mixed> */
    private static function doc(string $lesson, string $course): array
    {
        $p = static fn (string $t): array => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $t]]];

        return ['type' => 'doc', 'content' => [
            $p("This is a short demo lesson from \"{$course}\". Real lessons from HBM EduTech will replace it."),
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => $lesson]]],
            $p('Read each step, then try it on your own phone or at your hub. Ask a facilitator if you get stuck.'),
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Key points']]],
            ['type' => 'bulletList', 'content' => array_map(static fn (string $t): array => ['type' => 'listItem', 'content' => [$p($t)]], ['Go step by step.', 'Practise what you learn.', 'Ask for help when you need it.'])],
        ]];
    }
}
