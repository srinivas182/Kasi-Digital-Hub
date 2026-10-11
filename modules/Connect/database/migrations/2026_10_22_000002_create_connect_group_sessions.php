<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Group mentoring at hubs (career talks, business circles) - hub sessions hosted by a mentor (S18). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_group_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->string('event_id', 26);
            $table->string('title', 140);
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('minutes');
            $table->boolean('counted')->default(false); // minutes added to the mentor's hours after it took place
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_group_sessions');
    }
};
