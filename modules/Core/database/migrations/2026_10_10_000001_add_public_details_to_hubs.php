<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public hub details for the website (Sprint 5): web address, short description and contact details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->string('slug', 80)->nullable()->unique()->after('name')->comment('Web address, e.g. tsutsumani');
            $table->string('description', 400)->nullable()->after('slug');
            $table->string('phone', 16)->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'description', 'phone', 'email']);
        });
    }
};
