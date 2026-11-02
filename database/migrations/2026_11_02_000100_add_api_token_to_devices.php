<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Токен клієнта VR-освіта в окулярах (/api/v1/vr): зберігається лише SHA-256
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('api_token_hash', 64)->nullable()->unique();
            $table->timestamp('api_token_issued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn(['api_token_hash', 'api_token_issued_at']);
        });
    }
};
