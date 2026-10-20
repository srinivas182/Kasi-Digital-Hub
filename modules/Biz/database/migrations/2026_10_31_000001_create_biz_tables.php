<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiBiz books (Sprint 28): a cash book per KasiStart business, customers and suppliers, money owed, and the
 * history of every correction. Money in cents (ZAR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biz_books', function (Blueprint $table): void {
            $table->string('business_id', 26)->primary(); // the KasiStart business (via BusinessFacts)
            $table->json('opening')->nullable(); // {"cash": 30000, "card": 0, "mobile": 0} at started_on
            $table->date('started_on');
            $table->timestamp('synced_at')->nullable(); // last import from other portals
            $table->timestamps();
        });

        Schema::create('biz_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('business_id', 26)->index();
            $table->string('kind', 10); // customer | supplier
            $table->string('name', 80);
            $table->string('phone', 20)->nullable();
            $table->string('notes', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('biz_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('business_id', 26);
            $table->string('direction', 3); // in | out
            $table->unsignedInteger('amount_cents');
            $table->string('category', 20); // see Books::CATEGORIES (e.g. stock_materials, owner_put_in)
            $table->string('method', 8); // cash | card | mobile
            $table->date('occurred_on');
            $table->string('note', 300)->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('biz_contacts')->nullOnDelete();
            $table->foreignUlid('receipt_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('source', 10)->default('manual'); // manual | market | credit
            $table->string('source_ref', 60)->nullable(); // e.g. market:sale:KM-2026-000123 (imported once)
            $table->boolean('hidden')->default(false); // imported entries can be hidden, not edited
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes(); // removed entries stay in the history
            $table->index(['business_id', 'occurred_on']);
            $table->unique(['business_id', 'source_ref']);
        });

        Schema::create('biz_entry_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('entry_id')->constrained('biz_entries')->cascadeOnDelete();
            $table->string('action', 10); // edited | removed | hidden
            $table->json('before');
            $table->foreignUlid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });

        Schema::create('biz_credits', function (Blueprint $table): void {
            $table->id();
            $table->string('business_id', 26)->index();
            $table->foreignId('contact_id')->constrained('biz_contacts')->cascadeOnDelete();
            $table->string('direction', 8); // owed_me | i_owe
            $table->unsignedInteger('amount_cents');
            $table->string('description', 200);
            $table->date('due_on')->nullable();
            $table->foreignUlid('paid_entry_id')->nullable()->constrained('biz_entries')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['biz_credits', 'biz_entry_changes', 'biz_entries', 'biz_contacts', 'biz_books'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
