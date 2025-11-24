<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapists', function (Blueprint $t) {
            // ✅ حالة الدكتور في الشات (يفتح ويقفل من الداشبورد)
            $t->boolean('is_chat_online')
              ->default(false)
              ->after('is_active'); // عدّلي اسم العمود حسب الموجود عندك

            // ✅ آخر مرّة كان أونلاين
            $t->timestamp('last_online_at')
              ->nullable()
              ->after('is_chat_online');

            // ✅ صورة الدكتور (أفاتار) – ممكن تبقى URL أو path في storage
            $t->string('avatar')
              ->nullable()
              ->after('last_online_at');
        });
    }

    public function down(): void
    {
        Schema::table('therapists', function (Blueprint $t) {
            $t->dropColumn(['is_chat_online', 'last_online_at', 'avatar']);
        });
    }
};
