<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiBiz part 2 (Sprint 29): stock items and movements (linked items share KasiMarket's quantity), the compliance
 * calendar, and summaries shared with a partner (bands only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biz_stock_items', function (Blueprint $table): void {
            $table->id();
            $table->string('business_id', 26)->index();
            $table->string('name', 80);
            $table->string('unit', 10)->default('each'); // each | kg | litre | pack | metre
            $table->unsignedInteger('cost_cents')->default(0);
            $table->unsignedInteger('price_cents')->nullable();
            $table->integer('quantity')->default(0); // not used when linked to a KasiMarket product
            $table->unsignedInteger('reorder_level')->default(0);
            $table->string('market_product_id', 26)->nullable(); // via the core ProductStock contract
            $table->boolean('archived')->default(false);
            $table->timestamp('low_notified_on')->nullable(); // one low-stock message per day at most
            $table->timestamps();
            $table->unique(['business_id', 'market_product_id']);
        });

        Schema::create('biz_stock_moves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('biz_stock_items')->cascadeOnDelete();
            $table->string('kind', 10); // bought | sold | used | damaged | lost | counted
            $table->integer('change'); // + or -; for counted, the difference found
            $table->integer('after'); // quantity after the move
            $table->unsignedInteger('cost_cents')->nullable();
            $table->string('note', 200)->nullable();
            $table->foreignUlid('by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['item_id', 'created_at']);
        });

        Schema::create('biz_obligations', function (Blueprint $table): void {
            $table->id();
            $table->string('business_id', 26);
            $table->string('rule', 24); // cipc_annual | turnover_interim | turnover_annual | vat | emp201 | coida | bbbee | trading_permit
            $table->date('due_on');
            $table->string('status', 10)->default('due'); // due | done | dismissed
            $table->boolean('adjusted')->default(false); // the owner changed the date
            $table->timestamp('done_at')->nullable();
            $table->foreignUlid('document_id')->nullable()->constrained('documents')->nullOnDelete(); // proof, private
            $table->date('reminded_14')->nullable();
            $table->date('reminded_3')->nullable();
            $table->date('reminded_overdue')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'rule', 'due_on']);
            $table->index(['status', 'due_on']);
        });

        Schema::create('biz_shares', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('business_id', 26)->index();
            $table->string('token_hash', 64)->unique(); // for 7-day links; the token itself is shown once
            $table->string('referral_id', 26)->nullable(); // attached to a partner referral instead
            $table->string('recipient', 120)->nullable(); // who it is for, in the owner's words
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUlid('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('biz_share_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('share_id')->constrained('biz_shares')->cascadeOnDelete();
            $table->foreignUlid('viewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['biz_share_views', 'biz_shares', 'biz_obligations', 'biz_stock_moves', 'biz_stock_items'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
