<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What the compliance calendar needs to know that KasiStart doesn't hold (S29); the owner confirms it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biz_compliance_profiles', function (Blueprint $table): void {
            $table->string('business_id', 26)->primary();
            $table->date('registered_on')->nullable(); // CIPC registration date (anniversary for the annual return)
            $table->boolean('turnover_tax')->default(false);
            $table->boolean('vat_registered')->default(false);
            $table->boolean('employs')->default(false);
            $table->date('bbbee_on')->nullable(); // date of the last B-BBEE affidavit
            $table->date('permit_renewal_on')->nullable(); // the municipal trading permit's renewal date
            $table->boolean('confirmed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biz_compliance_profiles');
    }
};
