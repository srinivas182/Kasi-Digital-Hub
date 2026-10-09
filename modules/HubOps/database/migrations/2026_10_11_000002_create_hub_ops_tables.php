<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiHub Ops (Sprint 7): visits, events and event registrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per person per hub per day. Walk-ins who are not registered have no user.
        // After 24 months user_id is cleared (kasi:hub-ops:anonymise-visits) and only the count remains.
        Schema::create('hub_visits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('visit_date');
            $table->string('purpose', 24);
            $table->string('method', 12); // qr | desk | walk_in | event | assisted
            $table->foreignUlid('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['hub_id', 'user_id', 'visit_date']);
            $table->index(['hub_id', 'visit_date']);
            $table->index(['user_id', 'visit_date']);
        });

        Schema::create('hub_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->string('type', 16); // job_day | workshop | info_session | class
            $table->string('title', 140);
            $table->text('description')->nullable();
            $table->string('room', 80)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->string('audience', 12); // public | members | learners
            $table->string('status', 12)->default('scheduled'); // scheduled | cancelled
            $table->string('cancel_reason', 300)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hub_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });

        Schema::create('hub_event_registrations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained('hub_events')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 12); // registered | waitlisted | cancelled
            $table->foreignUlid('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attended_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_event_registrations');
        Schema::dropIfExists('hub_events');
        Schema::dropIfExists('hub_visits');
    }
};
