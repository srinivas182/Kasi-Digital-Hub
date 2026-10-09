<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S10: organisation profile (shown on job listings), community employers (no CIPC registration,
 * verified through ID, proof of address and a hub visit), and the verification checklist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('trading_name', 160)->nullable()->after('name');
            $table->string('sector', 32)->nullable()->after('type');
            $table->string('size_band', 12)->nullable()->after('sector'); // 1 | 2-10 | 11-50 | 51-200 | 200+
            $table->text('description')->nullable()->after('address');
            $table->string('website', 190)->nullable()->after('description');
            $table->boolean('community')->default(false)->after('website');
            $table->foreignUlid('registration_document_id')->nullable()->after('community')->constrained('documents')->nullOnDelete();
            $table->json('verification_checklist')->nullable()->after('registration_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('registration_document_id');
            $table->dropColumn(['trading_name', 'sector', 'size_band', 'description', 'website', 'community', 'verification_checklist']);
        });
    }
};
