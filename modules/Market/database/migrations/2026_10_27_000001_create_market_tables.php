<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** KasiMarket shops and products (Sprint 24). Money in cents (ZAR, VAT-inclusive). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('market_shops', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('business_id', 26)->unique(); // the KasiStart business (via BusinessFacts)
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->string('description', 600)->nullable();
            $table->foreignUlid('hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->string('area', 120)->nullable(); // where they trade, in their own words
            $table->string('whatsapp', 20)->nullable(); // business WhatsApp, E.164
            $table->boolean('whatsapp_public')->default(false); // shown only with the trader's consent
            $table->json('hours')->nullable(); // {"mon": "08:00-17:00", ...}
            $table->boolean('away')->default(false);
            $table->string('logo_path', 160)->nullable();
            $table->string('cover_path', 160)->nullable();
            $table->string('status', 10)->default('open'); // open | suspended | closed
            $table->unsignedSmallInteger('approved_products')->default(0); // first 3 are reviewed
            $table->foreignUlid('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'hub_id']);
        });

        Schema::create('market_products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('shop_id')->constrained('market_shops')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('market_categories');
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('price_cents');
            $table->string('unit', 20)->default('each'); // each | kg | hour | session | pack | metre
            $table->string('stock_type', 14)->default('quantity'); // quantity | made_to_order | service
            $table->unsignedInteger('stock')->nullable();
            $table->string('status', 10)->default('draft'); // draft | review | published | hidden | refused
            $table->string('review_reason', 200)->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'category_id']);
            $table->index(['shop_id', 'status']);
        });

        Schema::create('market_product_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('product_id')->constrained('market_products')->cascadeOnDelete();
            $table->string('label', 40); // e.g. "Size M", "Red"
            $table->unsignedInteger('price_cents')->nullable(); // null = the product price
            $table->unsignedInteger('stock')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('market_product_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('product_id')->constrained('market_products')->cascadeOnDelete();
            $table->string('small_path', 160); // ~320 px, ~15 KB
            $table->string('medium_path', 160); // ~800 px, ~60 KB
            $table->string('alt', 150);
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('market_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('product_id')->constrained('market_products')->cascadeOnDelete();
            $table->foreignUlid('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 20); // prohibited | scam | wrong_info | offensive | other
            $table->string('note', 500)->nullable();
            $table->string('status', 10)->default('open'); // open | actioned | dismissed
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['market_reports', 'market_product_photos', 'market_product_options', 'market_products', 'market_shops', 'market_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
