<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** KasiBrand part 2 (Sprint 31): one-page websites, their photos and private statistics, and the content calendar. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_sites', function (Blueprint $table): void {
            $table->string('business_id', 26)->primary();
            $table->string('name', 30)->nullable()->unique(); // the address: name.kasidigitalhub.co.za and /b/name
            $table->timestamp('name_changed_at')->nullable(); // an address can change once a month
            $table->string('about', 600)->nullable();
            $table->json('services')->nullable(); // typed in, when not taken from KasiMarket: [{"name","price"}]
            $table->boolean('use_market')->default(true); // show listed KasiMarket products
            $table->string('status', 10)->default('draft'); // draft | published | held | taken_down
            $table->string('held_reason', 300)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('brand_site_photos', function (Blueprint $table): void {
            $table->id();
            $table->string('business_id', 26)->index();
            $table->string('path', 160);
            $table->string('alt', 120);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamp('created_at');
        });

        Schema::create('brand_site_stats', function (Blueprint $table): void {
            $table->string('business_id', 26);
            $table->date('day');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('whatsapp_taps')->default(0);
            $table->unsignedInteger('order_taps')->default(0);
            $table->primary(['business_id', 'day']); // counts only - no cookies, no personal data
        });

        Schema::create('brand_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('business_id', 26)->index();
            $table->string('channel', 12); // whatsapp | facebook | instagram | poster
            $table->dateTime('planned_at');
            $table->string('text', 500);
            $table->foreignId('design_id')->nullable()->constrained('brand_designs')->nullOnDelete();
            $table->string('template', 16)->nullable(); // suggested template for "make the design"
            $table->string('status', 10)->default('planned'); // planned | posted | skipped
            $table->boolean('suggested')->default(false); // came from the AI month plan
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'planned_at']);
        });
    }

    public function down(): void
    {
        foreach (['brand_posts', 'brand_site_stats', 'brand_site_photos', 'brand_sites'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
