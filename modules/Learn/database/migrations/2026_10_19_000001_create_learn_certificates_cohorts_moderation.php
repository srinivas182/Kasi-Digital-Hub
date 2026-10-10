<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiLearn certificates, cohorts and moderation (Sprint 15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_provider_settings', function (Blueprint $table): void {
            $table->foreignUlid('organisation_id')->primary()->constrained('organisations')->cascadeOnDelete();
            $table->string('signatory_name', 120)->nullable();
            $table->string('signatory_title', 120)->nullable();
            $table->timestamps();
        });

        Schema::table('learn_courses', function (Blueprint $table): void {
            $table->unsignedTinyInteger('attendance_percent')->default(80)->after('delivery');
        });

        Schema::create('learn_certificates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrolment_id')->constrained('learn_enrolments')->cascadeOnDelete();
            $table->foreignUlid('generated_document_id')->nullable()->constrained('generated_documents')->nullOnDelete();
            $table->string('kind', 12); // completion | statement
            $table->boolean('show_on_cv')->default(true);
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 300)->nullable();
            $table->index(['enrolment_id', 'revoked_at']);
        });

        Schema::create('learn_cohorts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->constrained('learn_courses')->cascadeOnDelete();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUlid('hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 8)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('capacity');
            $table->string('status', 12)->default('open'); // pending_hub | open | closed | cancelled
            $table->string('status_reason', 300)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hub_id', 'status']);
        });

        Schema::create('learn_cohort_members', function (Blueprint $table): void {
            $table->foreignUlid('cohort_id')->constrained('learn_cohorts')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 10)->default('joined'); // joined | waiting | removed
            $table->foreignUlid('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('nudged_at')->nullable();
            $table->primary(['cohort_id', 'user_id']);
        });

        Schema::create('learn_cohort_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('cohort_id')->constrained('learn_cohorts')->cascadeOnDelete();
            $table->string('event_id', 26)->nullable(); // hub session (hub event)
            $table->string('title', 140);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('cancelled')->default(false);
        });

        Schema::create('learn_moderations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('submission_id')->unique()->constrained('learn_submissions')->cascadeOnDelete();
            $table->string('reason', 14); // sample | new_assessor | accredited
            $table->string('status', 10)->default('pending'); // pending | agreed | disagreed
            $table->foreignUlid('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['learn_moderations', 'learn_cohort_sessions', 'learn_cohort_members', 'learn_cohorts', 'learn_certificates', 'learn_provider_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('learn_courses', fn (Blueprint $table) => $table->dropColumn('attendance_percent'));
    }
};
