<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiWork matching (Sprint 11): stored match scores with reasons, invitations to apply, profile
 * views by employers (shown to the person), employers a person hides from, and skill synonyms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_matches', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('listing_id')->constrained('work_listings')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->json('reasons');
            $table->json('gaps'); // "you may not meet" items (licence, education)
            $table->unsignedSmallInteger('distance_km')->nullable();
            $table->timestamp('computed_at');
            $table->timestamp('alerted_at')->nullable();
            $table->primary(['user_id', 'listing_id']);
            $table->index(['listing_id', 'score']);
            $table->index(['user_id', 'score']);
        });

        Schema::create('work_invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('listing_id')->constrained('work_listings')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('sent'); // sent | accepted | declined | expired
            $table->string('message', 300)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['listing_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('work_profile_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUlid('viewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('listing_id')->nullable()->constrained('work_listings')->nullOnDelete();
            $table->timestamp('viewed_at');
            $table->index(['user_id', 'viewed_at']);
        });

        Schema::create('work_hidden_employers', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'organisation_id']);
        });

        Schema::create('work_skill_synonyms', function (Blueprint $table): void {
            $table->id();
            $table->string('phrase', 60)->unique();
            $table->string('canonical', 60)->index();
        });
    }

    public function down(): void
    {
        foreach (['work_skill_synonyms', 'work_hidden_employers', 'work_profile_views', 'work_invitations', 'work_matches'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
