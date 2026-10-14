<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S14 created learn_questions.kind as 8 characters, but "truefalse" is 9: MySQL rejects or cuts it, SQLite doesn't
 * notice. Found in S19 by scanning column lengths against their documented values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learn_questions', fn (Blueprint $table) => $table->string('kind', 12)->change());
    }

    public function down(): void
    {
        Schema::table('learn_questions', fn (Blueprint $table) => $table->string('kind', 8)->change());
    }
};
