<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cache of embeddings for short phrases (skill names, job titles). No personal data. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->string('model', 64);
            $table->string('text', 120);
            $table->longText('vector');
            $table->timestamp('created_at')->nullable();
            $table->unique(['model', 'text']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_embeddings');
    }
};
