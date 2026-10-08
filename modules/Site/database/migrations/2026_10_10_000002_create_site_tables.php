<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public website (Sprint 5): cookie-free daily page-view counts (no personal data).
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }
};
