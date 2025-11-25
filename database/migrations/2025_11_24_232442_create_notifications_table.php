<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('notifications', function (Blueprint $table) {
        $table->id();

        // نوع النوتيفيكيشن (system / session_upcoming / payment_success / admin_broadcast ...)
        $table->string('type');

        // عنوان عام (ممكن نسيبه فاضي لو هنستخدم lang files فقط)
        $table->string('title_en')->nullable();
        $table->string('title_ar')->nullable();

        // template أو body جاهز (اختياري – برضه ممكن نستخدم lang)
        $table->text('body_en')->nullable();
        $table->text('body_ar')->nullable();

        // Data ديناميكية (doctor_name, session_id, message, ...)
        $table->json('data')->nullable();

        // حالة النوتيفيكيشن نفسه (draft/scheduled/sent/cancelled)
        $table->string('status')->default('sent');

        // لو Admin عمل Broadcast
        $table->unsignedBigInteger('created_by')->nullable();

        // لو Scheduled
        $table->timestamp('scheduled_at')->nullable();
        $table->timestamp('sent_at')->nullable();

        $table->timestamps();

        $table->foreign('created_by')
            ->references('id')->on('users')
            ->nullOnDelete();
    });
}

public function down(): void
{
    Schema::dropIfExists('notifications');
}

};
