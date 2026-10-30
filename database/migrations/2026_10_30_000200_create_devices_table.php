<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hardware_serial')->nullable()->unique();
            $table->string('device_name')->nullable();
            $table->string('model')->nullable();
            $table->string('os_version')->nullable();
            $table->string('client_hub_version')->nullable();
            $table->unsignedTinyInteger('battery_level_percent')->nullable();
            $table->unsignedBigInteger('free_storage_bytes')->nullable();
            $table->jsonb('installed_lessons_state')->nullable();
            $table->string('status', 30)->default('active')->index(); // active, blocked, decommissioned_replaced, pending_pairing
            $table->string('pairing_code', 6)->nullable()->unique();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->uuid('replaced_by_device_id')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->unsignedBigInteger('last_monotonic_clock_ms')->nullable();
            $table->string('last_ip')->nullable();
            $table->timestamp('clock_tamper_detected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
