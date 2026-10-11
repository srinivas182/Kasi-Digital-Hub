<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiConnect mentoring (Sprint 18): vetted mentors, requests, mentorships with agreements and
 * sessions, moderated messages, and safeguarding concerns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_mentors', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('headline', 140);
            $table->text('bio')->nullable();
            $table->json('expertise'); // free-text areas: occupations, sectors, skills
            $table->json('goals'); // job | business | career | confidence
            $table->json('languages');
            $table->json('formats'); // phone | video | hub
            $table->foreignUlid('hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->unsignedInteger('municipality_id')->nullable();
            $table->string('availability', 200)->nullable();
            $table->unsignedTinyInteger('capacity')->default(3);
            $table->string('status', 10)->default('applied'); // applied | approved | paused | removed
            $table->string('status_reason', 300)->nullable();
            $table->boolean('minors_ok')->default(false); // police clearance verified
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('minutes_mentored')->default(0);
            $table->timestamps();
            $table->index(['status', 'hub_id']);
        });

        Schema::create('connect_mentor_checks', function (Blueprint $table): void {
            $table->foreignUlid('mentor_id')->constrained('connect_mentors', 'user_id')->cascadeOnDelete();
            $table->string('check', 24);
            $table->string('note', 300)->nullable();
            $table->foreignUlid('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->primary(['mentor_id', 'check']);
        });

        Schema::create('connect_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('mentee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->string('goal', 12);
            $table->string('message', 1000)->nullable();
            $table->string('status', 10)->default('pending'); // pending | accepted | declined | withdrawn
            $table->timestamps();
            $table->index(['mentor_id', 'status']);
        });

        Schema::create('connect_mentorships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('mentee_id')->constrained('users')->cascadeOnDelete();
            $table->string('goal', 12);
            $table->json('goals'); // the 1-3 agreed goals
            $table->unsignedTinyInteger('per_month')->default(2);
            $table->unsignedTinyInteger('months')->default(3);
            $table->string('format', 8);
            $table->string('status', 10)->default('proposed'); // proposed | active | paused | ended
            $table->timestamp('mentor_agreed_at')->nullable();
            $table->timestamp('mentee_agreed_at')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('renewed')->default(false);
            $table->string('ended_reason', 300)->nullable(); // private
            $table->foreignUlid('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('goals_met')->nullable();
            $table->string('mentee_feedback', 1000)->nullable();
            $table->string('mentor_feedback', 1000)->nullable();
            $table->timestamps();
            $table->index(['mentee_id', 'status']);
            $table->index(['mentor_id', 'status']);
        });

        Schema::create('connect_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('mentorship_id')->constrained('connect_mentorships')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('minutes')->default(60);
            $table->string('format', 8);
            $table->string('link', 255)->nullable();
            $table->string('event_id', 26)->nullable(); // hub session
            $table->string('status', 10)->default('scheduled'); // scheduled | held | cancelled
            $table->text('shared_notes')->nullable();
            $table->json('actions')->nullable();
            $table->text('private_notes')->nullable();
            $table->boolean('helpful')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('connect_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('mentorship_id')->constrained('connect_mentorships')->cascadeOnDelete();
            $table->foreignUlid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('held')->default(false);
            $table->timestamp('created_at');
        });

        Schema::create('connect_concerns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('about_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mentorship_id', 26)->nullable();
            $table->string('kind', 16); // inappropriate | contact | safety | other
            $table->boolean('urgent')->default(false);
            $table->text('body');
            $table->string('status', 10)->default('open'); // open | actioned | closed
            $table->foreignUlid('officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('actions')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'urgent', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['connect_concerns', 'connect_messages', 'connect_sessions', 'connect_mentorships', 'connect_requests', 'connect_mentor_checks', 'connect_mentors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
