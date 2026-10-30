<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Звернення з форми зворотного зв'язку на лендінгу
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->text('message')->nullable();
            $table->string('source', 30)->default('landing'); // landing, buy
            $table->boolean('is_processed')->default(false)->index();
            $table->timestamps();
        });

        // Пакет із лендінгу, за який оплачено онлайн (lesson, subject, school) і предмет для «Предметного курсу»
        Schema::table('payments', function (Blueprint $table) {
            $table->string('package', 30)->nullable()->after('promotion_id');
            $table->string('subject_category', 30)->nullable()->after('package');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['package', 'subject_category']));
        Schema::dropIfExists('leads');
    }
};
