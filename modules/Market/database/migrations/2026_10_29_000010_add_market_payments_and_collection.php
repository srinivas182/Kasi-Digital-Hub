<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiMarket payments and delivery (Sprint 26): online payment readiness per shop, delivery zones and free
 * delivery, payment state on orders, hub collection points with collection codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_shops', function (Blueprint $table): void {
            $table->boolean('accepts_online')->default(false);
            $table->boolean('accepts_cash')->default(true);
            $table->foreignUlid('bank_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->boolean('bank_verified')->default(false);
            $table->string('payout_subaccount', 60)->nullable(); // the trader's payout account at the provider
            $table->json('delivery_zones')->nullable(); // [{"name": "Giyani town", "fee_cents": 2000}], up to 5
            $table->unsignedInteger('free_delivery_over_cents')->nullable();
            $table->boolean('offers_hub_collection')->default(false);
        });

        Schema::table('market_orders', function (Blueprint $table): void {
            $table->string('payment', 8)->default('cash'); // cash | online
            $table->string('payment_status', 14)->default('none'); // none | pending | paid | part_refunded | refunded
            $table->string('payment_ref', 26)->nullable(); // the commercial payment reference
            $table->unsignedInteger('refunded_cents')->default(0);
            $table->timestamp('pay_by')->nullable(); // pending online payment holds stock until then
            $table->string('zone', 40)->nullable();
            $table->foreignUlid('collection_hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->string('collection_code_hash', 100)->nullable();
            $table->unsignedTinyInteger('code_attempts')->default(0);
            $table->timestamp('at_hub_at')->nullable();
        });

        Schema::create('market_collection_points', function (Blueprint $table): void {
            $table->foreignUlid('hub_id')->primary()->constrained('hubs')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('hours', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_collection_points');
        Schema::table('market_orders', fn (Blueprint $table) => $table->dropColumn(['payment', 'payment_status', 'payment_ref', 'refunded_cents', 'pay_by', 'zone', 'collection_hub_id', 'collection_code_hash', 'code_attempts', 'at_hub_at']));
        Schema::table('market_shops', fn (Blueprint $table) => $table->dropColumn(['accepts_online', 'accepts_cash', 'bank_document_id', 'bank_verified', 'payout_subaccount', 'delivery_zones', 'free_delivery_over_cents', 'offers_hub_collection']));
    }
};
