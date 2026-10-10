<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiLearn courses and authoring (Sprint 13): accreditation claims, courses, modules, lessons,
 * media (with low-data versions), published versions (frozen), review comments and saved courses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_accreditations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('body', 80); // QCTO or a SETA name
            $table->string('number', 60);
            $table->foreignUlid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 10)->default('pending'); // pending | verified | rejected
            $table->string('reason', 300)->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('learn_media', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('kind', 10); // image | video | audio | download
            $table->string('original_path');
            $table->string('original_name', 190);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('status', 12)->default('ready'); // processing | ready | failed
            $table->json('renditions')->nullable(); // {"low": {"path", "bytes"}, "standard": ..., "audio": ..., "thumb": ...}
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('alt', 300)->nullable();
            $table->foreignUlid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('learn_courses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('slug', 140)->unique();
            $table->string('title', 140);
            $table->string('summary', 600)->nullable();
            $table->json('outcomes')->nullable();
            $table->string('topic', 32);
            $table->string('level', 12)->default('beginner'); // beginner | intermediate | advanced
            $table->decimal('hours', 5, 1)->nullable();
            $table->string('prerequisites', 300)->nullable();
            $table->string('language', 24)->default('English');
            $table->ulid('translation_group')->nullable()->index();
            $table->unsignedTinyInteger('min_age')->default(18); // 16 | 18
            $table->string('delivery', 12)->default('self_paced'); // self_paced | blended | hub
            $table->foreignUlid('accreditation_id')->nullable()->constrained('learn_accreditations')->nullOnDelete();
            $table->unsignedTinyInteger('nqf_level')->nullable();
            $table->unsignedSmallInteger('credits')->nullable();
            $table->string('licence', 12)->default('all_rights'); // all_rights | cc_by | cc_by_sa
            $table->string('attribution', 300)->nullable();
            $table->foreignUlid('image_id')->nullable()->constrained('learn_media')->nullOnDelete();
            $table->string('status', 18)->default('draft'); // draft | submitted | in_review | published | unpublished
            $table->string('status_reason', 300)->nullable();
            $table->boolean('changed_since_publish')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'topic']);
        });

        Schema::create('learn_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('course_id')->constrained('learn_courses')->cascadeOnDelete();
            $table->string('title', 140);
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('learn_lessons', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->constrained('learn_courses')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('learn_modules')->cascadeOnDelete();
            $table->string('title', 140);
            $table->string('kind', 10); // text | video | audio | download | quiz | assignment
            $table->json('content')->nullable(); // editor document (structured, rendered safely on the server)
            $table->foreignUlid('media_id')->nullable()->constrained('learn_media')->nullOnDelete();
            $table->text('transcript')->nullable();
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('ai_drafted')->default(false);
            $table->boolean('preview')->default(false);
            $table->timestamps();
        });

        Schema::create('learn_course_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->constrained('learn_courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->json('snapshot'); // course, modules and lessons with rendered HTML
            $table->unsignedBigInteger('data_bytes')->default(0);
            $table->foreignUlid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->unique(['course_id', 'number']);
        });

        Schema::table('learn_courses', function (Blueprint $table): void {
            $table->foreignUlid('current_version_id')->nullable()->after('status_reason')->constrained('learn_course_versions')->nullOnDelete();
        });

        Schema::create('learn_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('course_id')->constrained('learn_courses')->cascadeOnDelete();
            $table->foreignUlid('lesson_id')->nullable()->constrained('learn_lessons')->nullOnDelete();
            $table->foreignUlid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 12)->default('comment'); // comment | submitted | approved | changes | published | unpublished
            $table->text('body')->nullable();
            $table->timestamp('created_at');
            $table->index(['course_id', 'created_at']);
        });

        Schema::create('learn_saved_courses', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('course_id')->constrained('learn_courses')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('notified_at')->nullable();
            $table->primary(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_saved_courses');
        Schema::dropIfExists('learn_reviews');
        Schema::table('learn_courses', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_version_id'));
        foreach (['learn_course_versions', 'learn_lessons', 'learn_modules', 'learn_courses', 'learn_media', 'learn_accreditations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
