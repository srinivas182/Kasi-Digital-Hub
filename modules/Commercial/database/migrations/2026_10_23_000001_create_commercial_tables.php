<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial and finance (Sprint 19): catalogue, prices with bands and four-eyes approval, split rules,
 * hub finance readiness, orders, an immutable ledger, payment-provider events, invoices, payouts and
 * reconciliation. All money in cents (ZAR); prices VAT-inclusive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_products', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('stream', 20); // package | work | learn | start | onsite | licence
            $table->string('name', 140);
            $table->string('unit', 30)->default('item');
            $table->string('merchant', 4); // hub | kasi (merchant of record)
            $table->foreignUlid('partner_organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('com_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('com_products')->cascadeOnDelete();
            $table->string('tier', 6); // metro | rural
            $table->unsignedInteger('price_cents'); // VAT-inclusive list price
            $table->unsignedInteger('floor_cents');
            $table->unsignedInteger('ceiling_cents');
            $table->date('effective_from');
            $table->string('status', 10)->default('pending'); // pending | active | rejected
            $table->foreignUlid('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'tier', 'status', 'effective_from']);
        });

        Schema::create('com_split_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('stream', 20);
            $table->unsignedSmallInteger('kasi_bp'); // basis points (100 = 1%)
            $table->unsignedSmallInteger('hub_bp');
            $table->unsignedSmallInteger('partner_bp')->default(0);
            $table->date('effective_from');
            $table->string('status', 10)->default('pending');
            $table->string('note', 300)->nullable();
            $table->foreignUlid('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['stream', 'status', 'effective_from']);
        });

        Schema::create('com_hub_finance', function (Blueprint $table): void {
            $table->foreignUlid('hub_id')->primary()->constrained('hubs')->cascadeOnDelete();
            $table->string('company_registration', 30)->nullable();
            $table->boolean('vat_registered')->default(false);
            $table->string('vat_number', 20)->nullable();
            $table->foreignUlid('bank_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('bank_account_label', 60)->nullable(); // e.g. "FNB ...4417" - never the full number
            $table->boolean('bank_verified')->default(false);
            $table->string('franchise_version', 30)->nullable();
            $table->timestamp('franchise_accepted_at')->nullable();
            $table->foreignUlid('franchise_accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_subaccount', 60)->nullable();
            $table->boolean('sales_blocked')->default(false); // unpaid package invoice > 30 days
            $table->timestamps();
        });

        Schema::create('com_approvals', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16); // price | split_rule | bank_account
            $table->string('subject_id', 26);
            $table->json('change');
            $table->foreignUlid('requested_by')->constrained('users');
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->string('reason', 300)->nullable();
            $table->timestamps();
            $table->index(['status', 'kind']);
        });

        Schema::create('com_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 20)->unique(); // human reference, e.g. KH-2026-000123
            $table->foreignId('product_id')->constrained('com_products');
            $table->foreignUlid('hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->string('attribution', 12); // referral | assisted | nearest | national | hub (POS / invoice)
            $table->foreignUlid('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('buyer_organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('buyer_name', 160)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('total_cents');
            $table->unsignedInteger('vat_cents');
            $table->string('merchant', 4);
            $table->foreignId('split_rule_id')->nullable()->constrained('com_split_rules');
            $table->json('shares'); // {"kasi": c, "hub": c, "partner": c}
            $table->string('channel', 8); // online | pos | invoice
            $table->string('status', 16)->default('pending'); // pending | paid | failed | refunded | part_refunded (13 chars - MySQL enforces lengths)
            $table->string('provider', 16)->nullable();
            $table->string('provider_ref', 80)->nullable()->unique();
            $table->unsignedInteger('refunded_cents')->default(0);
            $table->json('meta')->nullable(); // e.g. {"organisation": ..., "course": ...}
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['hub_id', 'status', 'paid_at']);
        });

        Schema::create('com_ledger', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id', 26)->nullable()->index();
            $table->string('invoice_id', 26)->nullable()->index();
            $table->foreignUlid('hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->foreignUlid('partner_organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('kind', 16); // sale | refund | royalty_due | package | payout | adjustment
            $table->string('account', 10); // kasi | hub | partner | vat
            $table->integer('amount_cents'); // signed; never edited - corrections are new entries
            $table->string('memo', 200)->nullable();
            $table->timestamp('created_at');
            $table->index(['hub_id', 'account', 'created_at']);
        });

        Schema::create('com_provider_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 16);
            $table->string('event_id', 100);
            $table->string('type', 60);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('com_invoices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 20)->unique();
            $table->string('kind', 10); // package | licence
            $table->foreignUlid('hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->foreignUlid('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->string('description', 200);
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('vat_cents');
            $table->unsignedInteger('delivery_fee_cents')->default(0); // licences: paid to the delivering hub
            $table->string('status', 8)->default('open'); // open | paid | void
            $table->date('due_on');
            $table->unsignedTinyInteger('reminders')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('generated_document_id', 26)->nullable();
            $table->timestamps();
            $table->unique(['kind', 'hub_id', 'organisation_id', 'period']);
        });

        Schema::create('com_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->string('period', 7);
            $table->integer('amount_cents');
            $table->string('status', 10)->default('planned'); // planned | paid | carried
            $table->string('reference', 80)->nullable();
            $table->timestamps();
            $table->unique(['hub_id', 'period']);
        });

        Schema::create('com_reconciliation_issues', function (Blueprint $table): void {
            $table->id();
            $table->date('settlement_date');
            $table->string('provider_ref', 80)->nullable();
            $table->string('issue', 30); // missing_in_ledger | missing_at_provider | amount_differs
            $table->integer('ours_cents')->nullable();
            $table->integer('theirs_cents')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['com_reconciliation_issues', 'com_payouts', 'com_invoices', 'com_provider_events', 'com_ledger', 'com_orders', 'com_approvals', 'com_hub_finance', 'com_split_rules', 'com_prices', 'com_products'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
