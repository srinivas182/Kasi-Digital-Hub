<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * National structure (Sprint 3): geography, hubs, hub module switches,
 * organisations, members and scoped role assignments.
 * See docs/sprints/S03-hierarchy-roles.md and ADR-011.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3)->unique()->comment('EC, FS, GP, KZN, LP, MP, NC, NW, WC');
            $table->string('name', 60);
            $table->timestamps();
        });

        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique()->comment('MDB code, e.g. JHB, LIM331, DC33');
            $table->string('name', 120);
            $table->string('category', 10)->comment('metro | district | local');
            $table->foreignId('province_id')->constrained();
            $table->foreignId('district_id')->nullable()->constrained('municipalities');
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('source', 120)->comment('Where this record came from (dataset and version)');
            $table->timestamps();
            $table->index(['province_id', 'category']);
        });

        Schema::create('places', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('municipality_id')->constrained();
            $table->string('name', 120);
            $table->string('kind', 16)->default('village')->comment('village | township | town | suburb');
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestamps();
            $table->unique(['municipality_id', 'name']);
        });

        Schema::create('organisations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('type', 20)->index()->comment('employer | training_provider | partner | funder | hub_operator | platform');
            $table->string('name', 160);
            $table->string('registration_number', 40)->nullable()->comment('CIPC or NPO number');
            $table->string('verification_status', 12)->default('pending')->index()->comment('pending | verified | rejected | suspended');
            $table->timestamp('verified_at')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 16)->nullable();
            $table->foreignId('municipality_id')->nullable()->constrained();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hubs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 24)->unique()->comment('e.g. LP-GIY-TSU');
            $table->string('name', 120);
            $table->foreignId('municipality_id')->constrained();
            $table->foreignUlid('place_id')->nullable()->constrained();
            $table->string('address')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->json('opening_hours')->nullable();
            $table->string('status', 10)->default('planned')->index()->comment('planned | live | paused');
            $table->string('package', 16)->default('base')->comment('base | growth | enterprise | full');
            $table->foreignUlid('operator_organisation_id')->nullable()->constrained('organisations');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hub_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('hub_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40);
            $table->boolean('enabled')->default(true);
            $table->string('source', 10)->comment('package | addon');
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['hub_id', 'module']);
        });

        Schema::create('organisation_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80)->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'user_id']);
        });

        Schema::create('role_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40)->index();
            $table->string('scope_type', 16)->comment('self | organisation | hub | municipality | province | national');
            $table->string('scope_id', 26)->nullable()->comment('Organisation/hub ULID, municipality/province id; null for self and national');
            $table->foreignUlid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'role', 'scope_type', 'scope_id']);
            $table->index(['scope_type', 'scope_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUlid('home_hub_id')->nullable()->after('preferred_locale')->constrained('hubs')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->after('home_hub_id')->constrained();
            $table->foreignId('municipality_id')->nullable()->after('province_id')->constrained();
            $table->string('place_name', 120)->nullable()->after('municipality_id')->comment('Village, township or suburb as the person wrote it');
            $table->unsignedInteger('access_version')->default(0)->after('two_factor_required')->comment('Bumped when roles change; part of the access cache key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('home_hub_id');
            $table->dropConstrainedForeignId('province_id');
            $table->dropConstrainedForeignId('municipality_id');
            $table->dropColumn(['place_name', 'access_version']);
        });

        foreach (['role_assignments', 'organisation_members', 'hub_modules', 'hubs', 'organisations', 'places', 'municipalities', 'provinces'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
