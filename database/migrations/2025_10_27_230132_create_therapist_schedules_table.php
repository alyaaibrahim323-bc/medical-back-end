<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('therapist_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained('therapists')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');    // 0=Sun .. 6=Sat
            $table->time('start_time');                // "09:00"
            $table->time('end_time');                  // "17:00"
            $table->unsignedSmallInteger('slot_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['therapist_id','weekday','is_active']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('therapist_schedules');
    }
};
