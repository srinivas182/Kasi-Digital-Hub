<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KasiStart business registration and formalisation (Sprint 16): businesses and owners, the editable
 * formalisation steps, each business's progress with proof, and the business plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('start_businesses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 140);
            $table->string('sells', 300)->nullable();
            $table->string('sector', 32);
            $table->string('stage', 12)->default('idea'); // idea | informal | registered
            $table->string('legal_form', 8)->nullable(); // sole | pty | coop | npc
            $table->unsignedInteger('municipality_id')->nullable();
            $table->string('place_name', 120)->nullable();
            $table->foreignUlid('hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->unsignedSmallInteger('people')->default(1); // people working in it, owners included
            $table->string('turnover_band', 12)->nullable(); // none | under_5k | 5k_20k | 20k_80k | over_80k (per month)
            $table->string('customers', 300)->nullable();
            $table->unsignedTinyInteger('readiness')->default(0);
            $table->timestamp('formalised_at')->nullable();
            $table->timestamps();
        });

        Schema::create('start_business_members', function (Blueprint $table): void {
            $table->foreignUlid('business_id')->constrained('start_businesses')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 8); // owner | coowner
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['business_id', 'user_id']);
        });

        Schema::create('start_steps', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('title', 160);
            $table->text('summary');
            $table->text('why')->nullable();
            $table->json('needs')->nullable();
            $table->text('where')->nullable();
            $table->string('link', 255)->nullable();
            $table->string('cost_note', 200)->nullable();
            $table->string('duration', 80)->nullable();
            $table->json('applies_to'); // {"forms": [...]|["*"], "sectors": [...]|["*"], "employees": true|null}
            $table->string('document_type', 32)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->date('last_checked_on')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('start_business_steps', function (Blueprint $table): void {
            $table->foreignUlid('business_id')->constrained('start_businesses')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('start_steps')->cascadeOnDelete();
            $table->foreignUlid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignUlid('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at');
            $table->primary(['business_id', 'step_id']);
        });

        Schema::create('start_plans', function (Blueprint $table): void {
            $table->foreignUlid('business_id')->primary()->constrained('start_businesses')->cascadeOnDelete();
            $table->json('sections'); // section key => text
            $table->json('numbers')->nullable(); // calculator inputs
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['start_plans', 'start_business_steps', 'start_steps', 'start_business_members', 'start_businesses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
