<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('school_or_org_name')->nullable();
            $table->string('contact_person_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('city')->nullable()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 30)->default('school_client')->index(); // super_admin, methodist_manager, finance_viewer, school_client
            $table->string('edrpou_tax_id', 20)->nullable()->index();
            $table->text('legal_address')->nullable();
            $table->string('iban_treasury', 34)->nullable();
            $table->string('kiosk_pin_code', 4)->default('0000');
            $table->unsignedInteger('low_balance_threshold')->default(10);
            $table->string('telegram_chat_id')->nullable();
            $table->string('status', 20)->default('active')->index(); // active, suspended, banned
            $table->text('admin_notes')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
