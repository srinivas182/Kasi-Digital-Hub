<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** KasiBrand (Sprint 30): one brand kit per business, saved designs, and a light log of downloads. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_kits', function (Blueprint $table): void {
            $table->string('business_id', 26)->primary();
            $table->string('logo_kind', 10)->default('text'); // text | upload
            $table->string('logo_path', 160)->nullable(); // uploaded logo (WebP)
            $table->string('logo_shape', 10)->default('circle'); // circle | square | rounded | badge | banner | plain
            $table->string('logo_text', 24)->nullable(); // initials or a short name for the text logo
            $table->string('primary', 7)->default('#1F4E79'); // hex
            $table->string('secondary', 7)->default('#F5B700');
            $table->string('lettering', 10)->default('modern'); // modern | friendly | classic
            $table->string('tagline', 60)->nullable();
            $table->json('contact')->nullable(); // {"whatsapp": true, "area": true, "hours": true} - what the owner chose to show
            $table->timestamps();
        });

        Schema::create('brand_designs', function (Blueprint $table): void {
            $table->id();
            $table->string('business_id', 26)->index();
            $table->string('template', 16); // flyer | poster | price_list | offer | now_open | hiring | square | status
            $table->string('title', 80);
            $table->json('fields'); // the plain fields the owner filled in
            $table->timestamps();
        });

        Schema::create('brand_exports', function (Blueprint $table): void {
            $table->id();
            $table->string('business_id', 26)->index();
            $table->string('template', 16);
            $table->string('format', 4); // png | pdf
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['brand_exports', 'brand_designs', 'brand_kits'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
