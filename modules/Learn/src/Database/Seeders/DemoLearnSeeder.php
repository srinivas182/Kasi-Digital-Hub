<?php

declare(strict_types=1);

namespace Modules\Learn\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Organisation;
use Modules\Learn\Models\Assignment;
use Modules\Learn\Models\Cohort;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\CourseModule;
use Modules\Learn\Models\Lesson;
use Modules\Learn\Models\Quiz;
use Modules\Learn\Services\Cohorts;
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
            if ($topic === 'customer_service') {
                self::assessments($course);
            }
            $workflow->publish($course, $herman);
        }

        app(RoleAssignments::class)->assign($herman, 'assessor_moderator', Scope::organisation($hbm));

        DB::table('learn_provider_settings')->insert(['organisation_id' => $hbm->id, 'signatory_name' => 'Dr Herman Moolman (demo)', 'signatory_title' => 'Director', 'created_at' => now(), 'updated_at' => now()]);

        // A blended cohort at Tsutsumani hub (S15) with a session next week.
        $customer = Course::query()->where('topic', 'customer_service')->first();
        $hub = Hub::query()->where('code', 'LP-GIY-TSU')->first();
        if ($customer !== null && $hub !== null) {
            $customer->forceFill(['delivery' => 'blended'])->save();
            $cohort = Cohort::query()->create(['course_id' => $customer->id, 'organisation_id' => $hbm->id, 'hub_id' => $hub->id, 'name' => 'Tsutsumani, October (demo)',
                'code' => 'KASI24', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addWeeks(6)->toDateString(), 'capacity' => 20, 'status' => 'open', 'created_by' => $herman->id, 'approved_by' => $herman->id]);
            app(Cohorts::class)->addSession($cohort->load('course'), 'Role-play: serving customers', now('Africa/Johannesburg')->addWeek()->setTime(10, 0)->toImmutable(),
                now('Africa/Johannesburg')->addWeek()->setTime(12, 0)->toImmutable(), 'Training room', $herman);
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

    /** A practice quiz, a graded quiz and an assignment for the customer service demo course (S14). */
    private static function assessments(Course $course): void
    {
        $module = CourseModule::query()->where('course_id', $course->id)->orderByDesc('position')->first();
        if ($module === null) {
            return;
        }
        $q = static fn (string $prompt, string $right, string $wrong, string $why): array => ['kind' => 'single', 'prompt' => $prompt, 'explanation' => $why,
            'options' => [['text' => $right, 'correct' => true, 'feedback' => null], ['text' => $wrong, 'correct' => false, 'feedback' => null]]];

        $practice = Lesson::query()->create(['course_id' => $course->id, 'module_id' => $module->id, 'title' => 'Practice: serving customers', 'kind' => 'quiz', 'position' => 10]);
        $quiz = Quiz::query()->create(['lesson_id' => $practice->id, 'graded' => false]);
        $quiz->questions()->create([...$q('A customer is waiting while you finish a phone call. What do you do?', 'Smile and show you will help them soon', 'Ignore them until the call ends', 'Letting people know you have seen them keeps them patient.'), 'position' => 0]);

        $graded = Lesson::query()->create(['course_id' => $course->id, 'module_id' => $module->id, 'title' => 'Quiz: customer service', 'kind' => 'quiz', 'position' => 11]);
        $quiz = Quiz::query()->create(['lesson_id' => $graded->id, 'graded' => true, 'pass_mark' => 50]);
        $quiz->questions()->create([...$q('A customer gives you R50 for a R32 item. How much change?', 'R18', 'R28', '50 - 32 = 18.'), 'position' => 0]);
        $quiz->questions()->create([...$q('An unhappy customer is shouting. What is the best first step?', 'Listen calmly and let them explain', 'Shout back so they stop', 'Listening calms the situation and shows respect.'), 'position' => 1]);

        $task = Lesson::query()->create(['course_id' => $course->id, 'module_id' => $module->id, 'title' => 'Assignment: handle a complaint', 'kind' => 'assignment', 'position' => 12]);
        Assignment::query()->create(['lesson_id' => $task->id, 'instructions' => 'A customer says the bread they bought this morning is stale. Write what you would say and do, step by step.',
            'rubric' => ['Listens and apologises', 'Offers a fair solution', 'Stays polite'], 'evidence' => ['text', 'photo']]);
    }
}
