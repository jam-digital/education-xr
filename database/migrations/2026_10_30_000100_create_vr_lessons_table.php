<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vr_lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('app_key')->unique();
            $table->string('subject_category', 30)->default('physics')->index(); // physics, chemistry, mathematics, biology, history, astronomy, stem_general
            $table->string('recommended_grades', 40)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('methodical_guide_pdf_url')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('banner_vr_url')->nullable();
            $table->string('preview_video_url')->nullable();
            $table->string('android_package_name')->unique();
            $table->string('meta_store_app_id')->nullable();
            $table->unsignedInteger('current_version_code')->nullable();
            $table->string('current_version_name')->nullable();
            $table->string('release_channel', 20)->default('production');
            $table->string('apk_storage_path')->nullable();
            $table->unsignedBigInteger('apk_size_bytes')->nullable();
            $table->string('apk_sha256_hash', 64)->nullable();
            $table->string('obb_storage_path')->nullable();
            $table->unsignedBigInteger('obb_size_bytes')->nullable();
            $table->string('obb_sha256_hash', 64)->nullable();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->unsignedInteger('default_tickets_count')->default(100);
            $table->unsignedInteger('grace_period_seconds')->default(90);
            $table->unsignedInteger('max_session_duration_minutes')->default(45);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Історія білдів: кожне завантаження APK/OBB — окремий запис; beta спершу йде на тестові окуляри
        Schema::create('lesson_builds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lesson_id')->constrained('vr_lessons')->cascadeOnDelete();
            $table->unsignedInteger('version_code');
            $table->string('version_name');
            $table->string('channel', 20)->default('beta'); // beta, production
            $table->string('apk_storage_path')->nullable();
            $table->unsignedBigInteger('apk_size_bytes')->nullable();
            $table->string('apk_sha256_hash', 64)->nullable();
            $table->string('obb_storage_path')->nullable();
            $table->unsignedBigInteger('obb_size_bytes')->nullable();
            $table->string('obb_sha256_hash', 64)->nullable();
            $table->string('hash_status', 20)->default('pending'); // pending, ready, failed
            $table->jsonb('beta_device_ids')->nullable();
            $table->text('release_notes')->nullable();
            $table->foreignUuid('uploaded_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['lesson_id', 'version_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_builds');
        Schema::dropIfExists('vr_lessons');
    }
};
