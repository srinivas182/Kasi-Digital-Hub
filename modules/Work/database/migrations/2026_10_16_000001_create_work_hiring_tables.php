<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiWork hiring (Sprint 12): applications with a timeline, team notes, interviews, messages and
 * retention check-ins; blind shortlisting per advert; "applying is open" notices for saved jobs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_listings', function (Blueprint $table): void {
            $table->boolean('blind_shortlisting')->default(false)->after('experience');
        });

        Schema::table('work_saved_jobs', function (Blueprint $table): void {
            $table->timestamp('notified_at')->nullable();
        });

        Schema::create('work_applications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('listing_id')->constrained('work_listings')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete(); // cleared when anonymised
            $table->foreignUlid('cv_id')->nullable()->constrained('work_cvs')->nullOnDelete();
            $table->string('stage', 14)->default('new'); // new | shortlisted | interview | offer | hired | unsuccessful | withdrawn
            $table->json('answers')->nullable();
            $table->string('message', 500)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('reference', 8); // short code used while names are hidden (blind shortlisting)
            $table->foreignUlid('assisted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reject_reason', 32)->nullable(); // private to the employer
            $table->date('hired_on')->nullable();
            $table->timestamp('hire_confirmed_at')->nullable();
            $table->timestamp('outcome_notified_at')->nullable();
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamp('anonymised_at')->nullable();
            $table->timestamps();
            $table->unique(['listing_id', 'user_id']);
            $table->index(['listing_id', 'stage']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('work_application_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('application_id')->constrained('work_applications')->cascadeOnDelete();
            $table->string('kind', 24); // submitted | stage | interview | withdrawn | hire_confirmed | retention
            $table->string('to_stage', 14)->nullable();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at');
            $table->index(['application_id', 'created_at']);
        });

        Schema::create('work_application_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('application_id')->constrained('work_applications')->cascadeOnDelete();
            $table->foreignUlid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at');
        });

        Schema::create('work_interviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('application_id')->constrained('work_applications')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->string('mode', 10); // in_person | hub | phone | video
            $table->string('place', 200)->nullable();
            $table->string('note', 300)->nullable();
            $table->string('status', 22)->default('proposed'); // proposed | confirmed | reschedule_requested | declined | cancelled
            $table->foreignUlid('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at']);
        });

        Schema::create('work_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('application_id')->constrained('work_applications')->cascadeOnDelete();
            $table->foreignUlid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('from_employer');
            $table->text('body');
            $table->boolean('held')->default(false); // waiting for review (scam / abuse rules)
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['application_id', 'created_at']);
        });

        Schema::create('work_retention_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('application_id')->constrained('work_applications')->cascadeOnDelete();
            $table->unsignedSmallInteger('days'); // 30 | 90
            $table->date('due_on');
            $table->timestamp('sent_at')->nullable();
            $table->string('answer', 4)->nullable(); // yes | no
            $table->timestamp('answered_at')->nullable();
            $table->unique(['application_id', 'days']);
            $table->index(['due_on', 'sent_at']);
        });
    }

    public function down(): void
    {
        foreach (['work_retention_checks', 'work_messages', 'work_interviews', 'work_application_notes', 'work_application_events', 'work_applications'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('work_saved_jobs', fn (Blueprint $table) => $table->dropColumn('notified_at'));
        Schema::table('work_listings', fn (Blueprint $table) => $table->dropColumn('blind_shortlisting'));
    }
};
