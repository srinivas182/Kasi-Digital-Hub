<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S26: marketplace sellers (e.g. KasiMarket traders) as a ledger party, identified by a payee reference. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_orders', fn (Blueprint $table) => $table->string('payee_ref', 40)->nullable()->index());
        Schema::table('com_ledger', fn (Blueprint $table) => $table->string('payee_ref', 40)->nullable()->index());
        // "trader" (6) didn't fit the original 4-character merchant columns.
        Schema::table('com_products', fn (Blueprint $table) => $table->string('merchant', 8)->change()); // hub | kasi | trader
        Schema::table('com_orders', fn (Blueprint $table) => $table->string('merchant', 8)->change()); // hub | kasi | trader
        Schema::table('com_ledger', fn (Blueprint $table) => $table->string('account', 10)->change()); // kasi | hub | partner | trader | vat
    }

    public function down(): void
    {
        Schema::table('com_orders', fn (Blueprint $table) => $table->dropColumn('payee_ref'));
        Schema::table('com_ledger', fn (Blueprint $table) => $table->dropColumn('payee_ref'));
    }
};
