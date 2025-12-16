<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapists', function (Blueprint $table) {
            // حالة الشات الفورية (اختيارية لو هتتحكموا فيها manual)
            if (!Schema::hasColumn('therapists', 'is_chat_online')) {
                $table->boolean('is_chat_online')->default(false)->index();
            }

            // أيام الإتاحة: ["Saturday","Sunday"] ... إلخ
            if (!Schema::hasColumn('therapists', 'chat_days')) {
                $table->json('chat_days')->nullable();
            }

            // وقت البداية والنهاية
            if (!Schema::hasColumn('therapists', 'chat_from')) {
                $table->time('chat_from')->nullable();
            }

            if (!Schema::hasColumn('therapists', 'chat_to')) {
                $table->time('chat_to')->nullable();
            }

            // آخر وقت كان Online (عندك بالفعل غالبًا، بس لو مش موجود)
            if (!Schema::hasColumn('therapists', 'last_online_at')) {
                $table->timestamp('last_online_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('therapists', function (Blueprint $table) {
            // drop indexes automatically handled by Laravel for most drivers
            if (Schema::hasColumn('therapists', 'is_chat_online')) {
                $table->dropColumn('is_chat_online');
            }
            if (Schema::hasColumn('therapists', 'chat_days')) {
                $table->dropColumn('chat_days');
            }
            if (Schema::hasColumn('therapists', 'chat_from')) {
                $table->dropColumn('chat_from');
            }
            if (Schema::hasColumn('therapists', 'chat_to')) {
                $table->dropColumn('chat_to');
            }
            if (Schema::hasColumn('therapists', 'last_online_at')) {
                $table->dropColumn('last_online_at');
            }
        });
    }
};
