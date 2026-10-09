<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiWork for job seekers (Sprint 9): profile, experience (formal and informal), education,
 * skills, languages and CV versions. Everything is removed with the account (cascade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_profiles', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('headline', 120)->nullable();
            $table->text('summary')->nullable();
            $table->string('drivers_licence', 8)->nullable(); // none | A1 | A | B | C1 | C | EB | EC1 | EC
            $table->boolean('own_transport')->default(false);
            $table->json('work_types')->nullable(); // full_time, part_time, piece_work, learnership, internship
            $table->json('sectors')->nullable();
            $table->unsignedSmallInteger('max_travel_km')->nullable();
            $table->string('available_from', 16)->nullable(); // now | 2_weeks | 1_month | date
            $table->boolean('show_age_on_cv')->default(false);
            $table->unsignedTinyInteger('completeness')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('work_experiences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16); // job | piece_work | own_business | family_business | caregiving | volunteer | learnership
            $table->string('title', 120);
            $table->string('organisation', 120)->nullable();
            $table->string('place', 120)->nullable();
            $table->string('started', 7)->nullable(); // YYYY-MM
            $table->string('ended', 7)->nullable();   // YYYY-MM, null = still doing it
            $table->string('duration', 60)->nullable(); // "about 2 years" when dates are not known
            $table->text('description')->nullable();
            $table->json('bullets')->nullable(); // accepted CV bullet points (AI-suggested or own)
            $table->json('suggested_bullets')->nullable(); // pending AI suggestion
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'position']);
        });

        Schema::create('work_education', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16); // school | matric | certificate | diploma | degree | learnership | short_course
            $table->string('name', 160); // e.g. "National Senior Certificate (Matric)", "Grade 11"
            $table->string('institution', 160)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('in_progress')->default(false);
            $table->string('details', 300)->nullable(); // subjects / results, optional
            $table->foreignUlid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 60);
            $table->unique(['user_id', 'name']);
        });

        Schema::create('work_languages', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('language', 24);
            $table->string('level', 12); // basic | good | fluent
            $table->unique(['user_id', 'language']);
        });

        Schema::create('work_cvs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('generated_document_id')->nullable()->constrained('generated_documents')->nullOnDelete();
            $table->string('template', 16);
            $table->json('content'); // frozen copy of what is on the CV
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['work_cvs', 'work_languages', 'work_skills', 'work_education', 'work_experiences', 'work_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
