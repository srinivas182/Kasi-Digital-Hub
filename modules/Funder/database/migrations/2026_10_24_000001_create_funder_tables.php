<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funder programmes (Sprint 20): scope, period, targets (changes need KasiHub approval), frozen monthly
 * snapshots of aggregate indicators, and consented, anonymised success stories. No personal data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_programmes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete(); // the funder
            $table->string('name', 160);
            $table->text('summary')->nullable();
            $table->json('scope'); // {"province_ids": [], "municipality_ids": [], "hub_ids": []} - empty = national
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedTinyInteger('age_min')->default(16);
            $table->unsignedTinyInteger('age_max')->default(35);
            $table->string('status', 10)->default('active'); // draft | active | closed
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fund_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('programme_id')->constrained('fund_programmes')->cascadeOnDelete();
            $table->string('indicator', 40);
            $table->unsignedBigInteger('target');
            $table->unique(['programme_id', 'indicator']);
        });

        Schema::create('fund_target_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('programme_id')->constrained('fund_programmes')->cascadeOnDelete();
            $table->string('indicator', 40);
            $table->unsignedBigInteger('target');
            $table->string('reason', 300);
            $table->foreignUlid('requested_by')->constrained('users');
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->timestamps();
        });

        Schema::create('fund_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_type', 12); // programme | province | municipality
            $table->string('scope_id', 26);
            $table->string('period', 7); // YYYY-MM
            $table->string('indicator', 40);
            $table->unsignedBigInteger('value'); // raw; suppression happens when shown
            $table->json('by_hub');
            $table->timestamp('frozen_at');
            $table->unique(['scope_type', 'scope_id', 'period', 'indicator']);
        });

        Schema::create('fund_stories', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->foreignUlid('programme_id')->nullable()->constrained('fund_programmes')->nullOnDelete();
            $table->string('title', 140);
            $table->text('body'); // anonymised: first name only or none
            $table->boolean('consent_recorded'); // the young person agreed, in writing, to this story being shared
            $table->foreignUlid('written_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('published'); // published | withdrawn
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['fund_stories', 'fund_snapshots', 'fund_target_changes', 'fund_targets', 'fund_programmes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
