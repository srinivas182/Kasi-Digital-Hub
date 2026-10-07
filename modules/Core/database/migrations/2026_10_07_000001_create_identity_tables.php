<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity (Sprint 2): accounts, sessions, devices, one-time codes, staff two-step
 * login, consent and guardian consent. See docs/sprints/S02-identity.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('phone', 16)->unique()->comment('E.164, e.g. +27724183390');
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('preferred_name', 80)->nullable();
            $table->date('date_of_birth');
            $table->string('preferred_locale', 8)->default('en');
            $table->string('pin')->nullable()->comment('Hashed 5-digit PIN');
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->string('status', 24)->default('active')->index()->comment('active | pending_guardian | suspended | deletion_requested');
            $table->string('age_band', 8)->default('adult')->comment('adult | minor');
            $table->boolean('whatsapp_opt_in')->default(false);
            $table->boolean('two_factor_required')->default(false)->comment('Set for staff, organisation and national roles');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('user_devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->nullable()->unique()->comment('SHA-256 of the remembered-device cookie token');
            $table->string('session_id')->nullable()->index();
            $table->string('name', 120);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('remembered_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_challenges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('phone', 16)->index();
            $table->string('purpose', 24)->comment('login | reset_pin | change_phone | guardian');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['phone', 'purpose', 'created_at']);
        });

        Schema::create('staff_two_factor', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('secret')->comment('Encrypted TOTP secret');
            $table->json('recovery_codes')->comment('Hashed one-time backup codes');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('consent_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->comment('terms | privacy');
            $table->unsignedInteger('version');
            $table->string('title');
            $table->text('summary');
            $table->longText('body');
            $table->timestamp('published_at');
            $table->timestamps();
            $table->unique(['key', 'version']);
        });

        Schema::create('consents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32)->index();
            $table->boolean('granted');
            $table->json('document_versions')->nullable()->comment('Document key => version accepted');
            $table->string('channel', 16)->default('self')->comment('self | assisted');
            $table->foreignUlid('assisted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('locale', 8)->default('en');
            $table->timestamp('created_at');
            $table->index(['user_id', 'purpose', 'created_at']);
        });

        Schema::create('guardian_consents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('guardian_name', 160);
            $table->string('guardian_phone', 16);
            $table->string('relationship', 40);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index()->comment('Account the event is about');
            $table->foreignUlid('actor_id')->nullable()->index()->comment('Who performed it, if not the user');
            $table->string('event', 64)->index();
            $table->string('outcome', 16)->default('success');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'guardian_consents', 'consents', 'consent_documents', 'staff_two_factor', 'otp_challenges', 'user_devices', 'sessions', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
