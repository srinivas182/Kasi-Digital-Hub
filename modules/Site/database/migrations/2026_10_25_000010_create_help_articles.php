<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Help centre articles (Sprint 21): reviewed before publishing, with "last checked" dates and feedback counts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('category', 16); // start | account | work | learn | business | mentoring | hubs | privacy
            $table->string('title', 160);
            $table->string('summary', 300);
            $table->text('body'); // simple safe format: paragraphs, "## " headings, "- " bullets
            $table->string('status', 10)->default('draft'); // draft | review | published
            $table->boolean('needs_review')->default(false); // starter content waiting for Ku Tirhisana's check
            $table->date('last_checked_on')->nullable();
            $table->unsignedInteger('helpful_yes')->default(0);
            $table->unsignedInteger('helpful_no')->default(0);
            $table->foreignUlid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
