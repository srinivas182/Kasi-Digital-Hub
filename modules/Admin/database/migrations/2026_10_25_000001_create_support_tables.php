<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support (Sprint 21): help requests with messages and internal notes, one-time codes that let support
 * staff make sensitive account changes with the person's agreement, and AI budget alerts sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 16)->unique();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 16); // account | work | learn | start | mentoring | hub | payments | safety | other
            $table->string('subject', 140);
            $table->string('status', 12)->default('open'); // open | waiting | resolved | escalated
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUlid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('from_staff');
            $table->boolean('internal')->default(false); // staff-only note
            $table->text('body');
            $table->foreignUlid('document_id')->nullable()->constrained('documents')->nullOnDelete(); // screenshot
            $table->timestamp('created_at');
        });

        Schema::create('support_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('requested_by')->constrained('users');
            $table->string('action', 16); // pin_reset | phone_change
            $table->string('code_hash', 100);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('ai_budget_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 64); // "platform" or a feature
            $table->string('period', 7);
            $table->unsignedTinyInteger('level'); // 80 | 100
            $table->timestamp('created_at');
            $table->unique(['scope', 'period', 'level']);
        });
    }

    public function down(): void
    {
        foreach (['ai_budget_alerts', 'support_codes', 'support_messages', 'support_tickets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
