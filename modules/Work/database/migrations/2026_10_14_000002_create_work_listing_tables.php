<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiWork for employers (Sprint 10): occupations (OFO), job listings with requirements and
 * screening questions, saved jobs and daily view counts (no personal data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofo_occupations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 16)->nullable()->unique(); // official OFO code, filled from the official list
            $table->string('title', 160);
            $table->unsignedTinyInteger('major_group'); // 1 Managers ... 9 Elementary occupations
            $table->boolean('official')->default(false); // true once checked against / imported from the official list
            $table->index('title');
        });

        Schema::create('work_listings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->foreignId('occupation_id')->nullable()->constrained('ofo_occupations')->nullOnDelete();
            $table->string('type', 16); // full_time | part_time | piece_work | learnership | internship | temporary
            $table->unsignedSmallInteger('positions')->default(1);
            $table->unsignedInteger('municipality_id')->nullable();
            $table->string('place_name', 120)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->unsignedInteger('pay_min_cents');
            $table->unsignedInteger('pay_max_cents')->nullable();
            $table->string('pay_period', 8); // hour | day | week | month
            $table->string('hours', 120)->nullable();
            $table->string('education', 16)->nullable(); // none | grade_9 | matric | certificate | diploma | degree
            $table->string('licence', 8)->nullable();
            $table->string('experience', 12)->default('none'); // none | some | 1_year | 2_years
            $table->json('languages')->nullable();
            $table->text('description');
            $table->date('closes_on');
            $table->string('status', 12)->default('draft'); // draft | review | live | closed | filled | expired | taken_down
            $table->string('status_reason', 300)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'closes_on']);
            $table->index(['organisation_id', 'status']);
            $table->index(['municipality_id', 'status']);
        });

        Schema::create('work_listing_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('listing_id')->constrained('work_listings')->cascadeOnDelete();
            $table->string('name', 60);
            $table->boolean('must')->default(true);
        });

        Schema::create('work_screening_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('listing_id')->constrained('work_listings')->cascadeOnDelete();
            $table->string('question', 200);
            $table->string('kind', 8)->default('yes_no'); // yes_no | short
            $table->unsignedTinyInteger('position')->default(0);
        });

        Schema::create('work_saved_jobs', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('listing_id')->constrained('work_listings')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'listing_id']);
        });

        Schema::create('work_listing_views', function (Blueprint $table): void {
            $table->foreignUlid('listing_id')->constrained('work_listings')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('views')->default(0);
            $table->primary(['listing_id', 'day']);
        });
    }

    public function down(): void
    {
        foreach (['work_listing_views', 'work_saved_jobs', 'work_screening_questions', 'work_listing_skills', 'work_listings', 'ofo_occupations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
