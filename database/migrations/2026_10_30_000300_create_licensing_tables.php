<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_licenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->constrained('vr_lessons')->cascadeOnDelete();
            $table->string('license_type', 30); // ticket_pool, time_subscription, trial, unlimited_school_promo
            $table->string('status', 20)->default('active')->index(); // active, paused, revoked, expired
            $table->unsignedInteger('tickets_total_purchased')->default(0);
            $table->integer('tickets_remaining')->default(0);
            $table->unsignedInteger('tickets_consumed')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable()->index();
            $table->boolean('auto_renew')->default(false);
            $table->string('payment_token_id')->nullable();
            $table->jsonb('allowed_device_ids')->nullable();
            $table->foreignUuid('updated_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'lesson_id']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('promo_type', 30); // all_subjects_bundle, subject_pack_physics_chem_math, new_lesson_release, discount_pack, trial_school_offer, promo_code
            $table->string('promo_code_str')->nullable()->unique();
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->string('currency', 3)->default('UAH');
            $table->jsonb('included_lesson_ids')->nullable();
            $table->unsignedInteger('grants_tickets_count')->nullable();
            $table->unsignedInteger('grants_duration_days')->nullable();
            $table->jsonb('target_user_ids')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->string('batch_label')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->nullable()->constrained('vr_lessons')->nullOnDelete();
            $table->foreignUuid('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('UAH');
            $table->string('payment_method', 30); // card_online, bank_invoice_school, manual_admin
            $table->string('gateway_name')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->unsignedInteger('tickets_added')->default(0);
            $table->unsignedInteger('subscription_days_added')->default(0);
            $table->string('status', 20)->default('pending')->index(); // pending, completed, failed, refunded
            $table->string('invoice_pdf_url')->nullable();
            $table->string('act_pdf_url')->nullable();
            $table->text('comment')->nullable();
            $table->foreignUuid('confirmed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('ticket_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('license_id')->nullable()->constrained('lesson_licenses')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->constrained('vr_lessons')->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->uuid('session_uuid')->unique();
            $table->string('mode', 30); // online_realtime, offline_cached_sync, grace_period_reentry, admin_adjustment
            $table->integer('tickets_delta');
            $table->timestamp('started_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedInteger('session_duration_sec')->nullable();
            $table->string('status', 30); // active, completed, crashed_grace_reused, denied_no_tickets, denied_clock_tamper
            $table->timestamps();
        });

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('target_user_id')->nullable()->index();
            $table->uuid('target_device_id')->nullable()->index();
            $table->string('action_type', 40)->index();
            $table->jsonb('old_value')->nullable();
            $table->jsonb('new_value')->nullable();
            $table->text('reason_comment')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('ticket_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('lesson_licenses');
    }
};
