<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4: document vault, notifications (preferences, deliveries), the platform
 * event log and the "what changed and why" updates feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->index();
            $table->string('disk', 32);
            $table->string('path')->comment('Private storage path - never public');
            $table->string('original_name', 190);
            $table->string('mime_type', 80);
            $table->unsignedInteger('size_bytes');
            $table->string('sha256', 64)->index();
            $table->string('status', 16)->default('pending_scan')->index()->comment('pending_scan | uploaded | verified | rejected | quarantined');
            $table->string('rejection_reason')->nullable();
            $table->foreignUlid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->foreignUlid('uploaded_by')->nullable()->comment('Set when a facilitator uploads on someone\'s behalf')->constrained('users')->nullOnDelete();
            $table->timestamp('expiry_reminded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_shares', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 120);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'revoked_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 24);
            $table->string('channel', 16);
            $table->boolean('enabled');
            $table->timestamps();
            $table->unique(['user_id', 'category', 'channel']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification', 64)->index();
            $table->string('category', 24);
            $table->string('channel', 16);
            $table->string('status', 16)->index()->comment('queued | held | sent | failed | skipped');
            $table->string('skip_reason', 40)->nullable();
            $table->string('dedupe_key', 120)->nullable()->index();
            $table->json('payload')->comment('Rendered title/body/url and template parameters');
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->string('provider_reference', 120)->nullable();
            $table->string('error')->nullable();
            $table->unsignedInteger('cost_cents')->default(0)->comment('Estimated cost in ZAR cents');
            $table->foreignUlid('fallback_for')->nullable()->comment('Delivery this SMS replaces after a failed WhatsApp message');
            $table->timestamps();
            $table->index(['user_id', 'channel', 'created_at']);
        });

        Schema::create('platform_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 64)->index();
            $table->foreignUlid('actor_id')->nullable()->index();
            $table->string('subject_type', 40)->nullable();
            $table->string('subject_id', 26)->nullable();
            $table->foreignUlid('user_id')->nullable()->index()->comment('Person the event is about');
            $table->foreignUlid('hub_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['name', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('updates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 32)->comment('Portal the update belongs to');
            $table->string('category', 24);
            $table->string('title');
            $table->string('body', 500)->nullable();
            $table->string('cause', 255)->nullable()->comment('What caused this update (the "why")');
            $table->string('url')->nullable();
            $table->foreignUlid('event_id')->nullable()->comment('Platform event that caused it');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        foreach (['updates', 'platform_events', 'notification_deliveries', 'notification_preferences', 'document_shares', 'documents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
