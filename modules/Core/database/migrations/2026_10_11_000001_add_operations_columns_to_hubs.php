<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hub operations (Sprint 7): the hub's internet connection(s) for the higher sign-in code limit,
 * and the secret behind the door-screen link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->json('trusted_ips')->nullable()->after('email');
            $table->string('kiosk_token_hash', 64)->nullable()->after('trusted_ips');
        });
    }

    public function down(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->dropColumn(['trusted_ips', 'kiosk_token_hash']);
        });
    }
};
