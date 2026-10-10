<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partners and referrals (Sprint 17): data-sharing agreements, support offers with structured
 * eligibility, consented referrals with a timeline, messages and follow-ups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUlid('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('version', 20);
            $table->timestamp('accepted_at');
        });

        Schema::create('partner_offers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('title', 140);
            $table->string('type', 16); // grant | loan | equipment | training | mentoring | market | legal
            $table->text('description');
            $table->unsignedInteger('value_min_cents')->nullable();
            $table->unsignedInteger('value_max_cents')->nullable();
            $table->date('opens_on');
            $table->date('closes_on')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->json('criteria'); // stages, sectors, forms, province_ids, municipality_ids, hub_ids, age_max, age_min, turnover, steps, min_readiness
            $table->json('documents')->nullable(); // required document types
            $table->string('status', 10)->default('draft'); // draft | review | open | closed | rejected
            $table->string('status_reason', 300)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'closes_on']);
        });

        Schema::create('partner_referrals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('offer_id')->constrained('partner_offers')->cascadeOnDelete();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('business_id', 26)->index();
            $table->foreignUlid('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('assisted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('facilitator_note')->nullable();
            $table->json('shared'); // consented items: profile, summary, readiness, documents: [ids]
            $table->string('message', 1000)->nullable();
            $table->string('stage', 12)->default('new'); // new | reviewing | info | approved | declined | withdrawn | no_response | outcome
            $table->string('decline_reason', 32)->nullable(); // private to the partner
            $table->string('outcome', 300)->nullable();
            $table->unsignedInteger('outcome_value_cents')->nullable();
            $table->timestamp('outcome_confirmed_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'stage']);
        });

        Schema::create('partner_referral_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('referral_id')->constrained('partner_referrals')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('stage', 12)->nullable();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('partner_referral_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('referral_id')->constrained('partner_referrals')->cascadeOnDelete();
            $table->foreignUlid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('from_partner');
            $table->text('body');
            $table->boolean('held')->default(false);
            $table->timestamp('created_at');
        });

        Schema::create('partner_followups', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('referral_id')->constrained('partner_referrals')->cascadeOnDelete();
            $table->unsignedTinyInteger('months'); // 3 | 6
            $table->date('due_on');
            $table->timestamp('sent_at')->nullable();
            $table->string('answer', 4)->nullable(); // yes | no
            $table->timestamp('answered_at')->nullable();
            $table->unique(['referral_id', 'months']);
        });

        Schema::create('partner_document_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('referral_id')->constrained('partner_referrals')->cascadeOnDelete();
            $table->foreignUlid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUlid('viewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('viewed_at');
        });
    }

    public function down(): void
    {
        foreach (['partner_document_views', 'partner_followups', 'partner_referral_messages', 'partner_referral_events', 'partner_referrals', 'partner_offers', 'partner_agreements'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
