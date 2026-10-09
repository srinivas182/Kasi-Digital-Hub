<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A plain flag for "has gaps", so queries never compare JSON values (which behaves differently
 * on MySQL and SQLite - found by CI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_matches', function (Blueprint $table): void {
            $table->boolean('has_gaps')->default(false)->after('gaps');
            $table->index(['listing_id', 'has_gaps', 'score']);
        });
    }

    public function down(): void
    {
        Schema::table('work_matches', function (Blueprint $table): void {
            $table->dropIndex(['listing_id', 'has_gaps', 'score']);
            $table->dropColumn('has_gaps');
        });
    }
};
