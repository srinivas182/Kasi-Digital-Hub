<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S22: indicators group events by hub within a name and period; user_id makes "different people" counts index-only. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_events', fn (Blueprint $table) => $table->index(['name', 'hub_id', 'occurred_at', 'user_id'], 'platform_events_insights'));
    }

    public function down(): void
    {
        Schema::table('platform_events', fn (Blueprint $table) => $table->dropIndex('platform_events_insights'));
    }
};
