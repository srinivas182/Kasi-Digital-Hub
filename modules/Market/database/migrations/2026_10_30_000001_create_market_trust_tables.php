<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** KasiMarket trust and safety (Sprint 27): reviews, disputes with private evidence, no-shows, strikes, shop reports. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('order_id')->unique()->constrained('market_orders')->cascadeOnDelete(); // one per order
            $table->foreignUlid('shop_id')->constrained('market_shops')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->string('body', 500)->nullable();
            $table->string('status', 10)->default('sent'); // sent | held | removed
            $table->string('reply', 500)->nullable();
            $table->string('reply_status', 10)->nullable(); // sent | held | removed
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'status']);
        });

        Schema::create('market_disputes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->unique()->constrained('market_orders')->cascadeOnDelete();
            $table->foreignUlid('opened_by')->constrained('users');
            $table->string('reason', 16); // not_received | not_described | damaged | charged_wrongly | other
            $table->string('details', 1000);
            $table->string('status', 12)->default('open'); // open | responded | settled | resolved
            $table->string('trader_response', 1000)->nullable();
            $table->timestamp('respond_by');
            $table->string('outcome', 12)->nullable(); // refund_full | refund_part | no_refund | mediated | settled
            $table->unsignedInteger('outcome_cents')->nullable();
            $table->string('outcome_note', 500)->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('market_dispute_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('dispute_id')->constrained('market_disputes')->cascadeOnDelete();
            $table->foreignUlid('document_id')->constrained('documents')->cascadeOnDelete(); // private, in the vault
            $table->foreignUlid('added_by')->constrained('users');
            $table->timestamp('created_at');
        });

        Schema::create('market_no_shows', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('order_id')->unique()->constrained('market_orders')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('shop_id')->constrained('market_shops')->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->index(['buyer_id', 'created_at']);
        });

        Schema::create('market_strikes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('shop_id')->constrained('market_shops')->cascadeOnDelete();
            $table->string('reason', 300);
            $table->string('source', 10); // report | dispute | other
            $table->foreignUlid('given_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
            $table->index(['shop_id', 'expires_at']);
        });

        Schema::table('market_shops', function (Blueprint $table): void {
            $table->string('suspended_reason', 300)->nullable();
            $table->timestamp('suspended_at')->nullable();
        });

        Schema::table('market_reports', function (Blueprint $table): void {
            $table->foreignUlid('shop_id')->nullable()->constrained('market_shops')->cascadeOnDelete(); // S27: report a whole shop
        });
        Schema::table('market_reports', fn (Blueprint $table) => $table->char('product_id', 26)->nullable()->change()); // same type as the foreign key, now optional
    }

    public function down(): void
    {
        foreach (['market_strikes', 'market_no_shows', 'market_dispute_files', 'market_disputes', 'market_reviews'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('market_shops', fn (Blueprint $table) => $table->dropColumn(['suspended_reason', 'suspended_at']));
    }
};
