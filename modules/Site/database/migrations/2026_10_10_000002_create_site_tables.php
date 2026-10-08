<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public website (Sprint 5): enquiries from the contact, employer and funder forms, and
 * cookie-free daily page-view counts (no personal data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('kind', 16)->index()->comment('contact | employer | funder');
            $table->string('name', 120);
            $table->string('organisation', 160)->nullable();
            $table->string('phone', 16)->nullable();
            $table->string('email')->nullable();
            $table->string('topic', 40)->nullable();
            $table->foreignUlid('hub_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->string('status', 12)->default('new')->index()->comment('new | handled | spam');
            $table->string('ip_hash', 64)->nullable()->comment('Hashed, for abuse control only');
            $table->timestamps();
        });

        Schema::create('page_views', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('page', 80)->comment('Route name, never the full URL');
            $table->unsignedInteger('views')->default(0);
            $table->unique(['day', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('enquiries');
    }
};
