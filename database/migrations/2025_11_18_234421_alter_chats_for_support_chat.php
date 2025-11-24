<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $t) {
            // 1) نفكّ الـ foreign key القديم على therapy_session_id
            // اسم الـ FK في الغالب: chats_therapy_session_id_foreign
            $t->dropForeign(['therapy_session_id']);

            // 2) نشيل الـ unique index على therapy_session_id
            // الاسم هو اللي طلعلك في اللوج: chats_therapy_session_id_unique
            $t->dropUnique('chats_therapy_session_id_unique');

            // 3) نخلي therapy_session_id و therapist_id nullable
            // NOTE: لو change() اشتكى بعدين، هقولك تعملي composer require doctrine/dbal
            $t->unsignedBigInteger('therapy_session_id')->nullable()->change();
            $t->unsignedBigInteger('therapist_id')->nullable()->change();

            // 4) نضيف نوع الشات: support / session
            $t->enum('type', ['support', 'session'])
                ->default('support')
                ->after('id');

            // 5) نرجّع الـ foreign key تاني على therapy_session_id (بس دلوقتي nullable)
            $t->foreign('therapy_session_id')
                ->references('id')
                ->on('therapy_sessions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $t) {
            // لو عايزة ترجع زي الأول (اختياري)

            // نفكّ الـ FK الجديد
            $t->dropForeign(['therapy_session_id']);

            // نشيل عمود type
            $t->dropColumn('type');

            // (لو عايزة ترجّعيهم NOT NULL و unique تاني، هتحتاجي تضيفي change() + unique هنا)
            // $t->unsignedBigInteger('therapy_session_id')->nullable(false)->change();
            // $t->unsignedBigInteger('therapist_id')->nullable(false)->change();
            // $t->unique('therapy_session_id');

            // وترجّعي الـ FK القديم لو حابة
            $t->foreign('therapy_session_id')
                ->references('id')
                ->on('therapy_sessions')
                ->onDelete('cascade');
        });
    }
};
