<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 8: AI layer (requests, feature switches, moderation, translations), search documents
 * (database search driver) and generated documents (PDFs with verification and share links).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('feature', 64);
            $table->string('prompt_key', 64);
            $table->unsignedSmallInteger('prompt_version');
            $table->string('model', 64);
            $table->string('tier', 8);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('outcome', 16); // ok | invalid | unavailable | disabled | budget
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            // Redacted input and the output, kept for quality review then cleared (retention_days).
            $table->text('input')->nullable();
            $table->text('output')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['feature', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['hub_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('ai_feature_settings', function (Blueprint $table): void {
            $table->string('feature', 64)->primary(); // '*' is the switch for all AI
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('monthly_budget_cents')->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_translations', function (Blueprint $table): void {
            $table->id();
            $table->char('source_hash', 64);
            $table->string('locale', 8);
            $table->text('text');
            $table->timestamps();
            $table->unique(['source_hash', 'locale']);
        });

        Schema::create('moderation_flags', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('subject_type', 64);
            $table->string('subject_id', 26);
            $table->foreignUlid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('excerpt');
            $table->json('reasons');
            $table->string('source', 8); // rules | ai
            $table->string('status', 12)->default('pending'); // pending | approved | rejected | escalated
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_reason', 300)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('search_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->string('ref', 64);
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->string('url', 255);
            $table->string('visibility', 12); // public | members | learners
            $table->foreignUlid('hub_id')->nullable()->constrained('hubs')->cascadeOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['type', 'ref']);
            $table->index('visibility');
        });

        Schema::create('generated_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32);
            $table->unsignedSmallInteger('template_version');
            $table->string('title', 200);
            $table->string('disk', 32);
            $table->string('path', 255);
            $table->char('sha256', 64);
            $table->string('verification_code', 16)->unique();
            $table->boolean('show_name')->default(true);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 26)->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 300)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('document_share_links', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('generated_document_id')->constrained('generated_documents')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['document_share_links', 'generated_documents', 'search_documents', 'moderation_flags', 'ai_translations', 'ai_feature_settings', 'ai_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
