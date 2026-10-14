<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paid extra advert slots (S19): each adds one live advert above the free limit for 30 days. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_advert_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->unsignedSmallInteger('slots');
            $table->timestamp('expires_at');
            $table->string('order_number', 20)->nullable();
            $table->timestamp('created_at');
            $table->index(['organisation_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_advert_slots');
    }
};
