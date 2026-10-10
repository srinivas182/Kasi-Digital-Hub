<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiLearn learning and assessments (Sprint 14): quizzes and assignments (authoring), enrolments tied
 * to a course version, progress per lesson, quiz attempts and assignment submissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('lesson_id')->unique()->constrained('learn_lessons')->cascadeOnDelete();
            $table->boolean('graded')->default(true); // false = practice (offline, instant feedback)
            $table->unsignedTinyInteger('pass_mark')->default(70);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->boolean('shuffle')->default(true);
        });

        Schema::create('learn_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('learn_quizzes')->cascadeOnDelete();
            $table->string('kind', 8); // single | multiple | truefalse
            $table->string('prompt', 500);
            $table->json('options'); // [{"text": ..., "correct": bool, "feedback": ...}]
            $table->string('explanation', 500)->nullable();
            $table->boolean('ai_drafted')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('learn_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('lesson_id')->unique()->constrained('learn_lessons')->cascadeOnDelete();
            $table->text('instructions');
            $table->json('rubric'); // list of criteria
            $table->json('evidence'); // allowed: text, photo, pdf
            $table->unsignedTinyInteger('max_resubmissions')->default(2);
        });

        Schema::create('learn_enrolments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('course_id')->constrained('learn_courses')->cascadeOnDelete();
            $table->foreignUlid('version_id')->constrained('learn_course_versions');
            $table->string('status', 10)->default('active'); // active | left | completed
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('last_lesson_id', 26)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'status']);
        });

        Schema::create('learn_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('enrolment_id')->constrained('learn_enrolments')->cascadeOnDelete();
            $table->string('lesson_id', 26); // id inside the frozen version
            $table->unsignedInteger('position')->default(0); // seconds into video/audio
            $table->unsignedTinyInteger('practice_score')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['enrolment_id', 'lesson_id']);
        });

        Schema::create('learn_quiz_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrolment_id')->constrained('learn_enrolments')->cascadeOnDelete();
            $table->string('lesson_id', 26);
            $table->json('answers');
            $table->unsignedTinyInteger('score');
            $table->boolean('passed');
            $table->timestamp('created_at');
            $table->index(['enrolment_id', 'lesson_id']);
        });

        Schema::create('learn_submissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrolment_id')->constrained('learn_enrolments')->cascadeOnDelete();
            $table->string('lesson_id', 26);
            $table->text('text')->nullable();
            $table->foreignUlid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 16)->default('submitted'); // submitted | competent | not_yet
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->json('rubric')->nullable(); // criteria met (assessor)
            $table->text('feedback')->nullable();
            $table->foreignUlid('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['enrolment_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        foreach (['learn_submissions', 'learn_quiz_attempts', 'learn_progress', 'learn_enrolments', 'learn_assignments', 'learn_questions', 'learn_quizzes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
