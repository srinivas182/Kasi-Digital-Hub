<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiMarket ordering (Sprint 25): carts, orders (one per shop, in-app or WhatsApp), frozen order lines, order
 * messages, and the shop's collection and delivery settings. Money in cents (ZAR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_shops', function (Blueprint $table): void {
            $table->boolean('offers_collection')->default(true);
            $table->string('collection_note', 200)->nullable(); // where and when to collect, in the trader's words
            $table->boolean('offers_delivery')->default(false);
            $table->string('delivery_area', 120)->nullable();
            $table->unsignedInteger('delivery_fee_cents')->default(0);
            $table->unsignedInteger('min_order_cents')->default(0);
        });

        Schema::create('market_cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('market_products')->cascadeOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('market_product_options')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
            $table->unique(['user_id', 'product_id', 'option_id']);
        });

        Schema::create('market_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 20)->unique(); // KM-2026-000123
            $table->foreignUlid('shop_id')->constrained('market_shops');
            $table->foreignUlid('buyer_id')->constrained('users');
            $table->foreignUlid('placed_by')->nullable()->constrained('users')->nullOnDelete(); // a facilitator in an assisted session
            $table->string('channel', 10); // app | whatsapp
            $table->string('fulfilment', 10); // collection | delivery
            $table->string('delivery_address', 200)->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('items_cents');
            $table->unsignedInteger('delivery_fee_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->string('status', 16)->default('placed'); // placed | accepted | declined | ready | out_for_delivery | completed | cancelled | lapsed
            $table->string('status_reason', 300)->nullable();
            $table->string('eta', 60)->nullable(); // trader's estimate, e.g. "Saturday 10:00"
            $table->boolean('phone_shared')->default(false); // the buyer chose to share their phone with the trader
            $table->boolean('buyer_confirmed')->default(false);
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'status']);
            $table->index(['buyer_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('market_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('order_id')->constrained('market_orders')->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('market_products')->nullOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('market_product_options')->nullOnDelete();
            $table->string('title', 100); // frozen at ordering time
            $table->string('option_label', 40)->nullable();
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedSmallInteger('quantity');
            $table->boolean('reserved')->default(false); // stock was taken and must be given back if the order doesn't go ahead
        });

        Schema::create('market_order_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('market_orders')->cascadeOnDelete();
            $table->foreignUlid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('status', 10)->default('sent'); // sent | held
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['market_order_messages', 'market_order_items', 'market_orders', 'market_cart_items'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('market_shops', fn (Blueprint $table) => $table->dropColumn(['offers_collection', 'collection_note', 'offers_delivery', 'delivery_area', 'delivery_fee_cents', 'min_order_cents']));
    }
};
