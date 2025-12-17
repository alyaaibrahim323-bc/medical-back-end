<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('therapist_chat_availabilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('therapist_id')
                ->constrained('therapists')
                ->cascadeOnDelete();

            $table->tinyInteger('day_of_week'); // 0=Sun ... 6=Sat
            $table->time('from_time');
            $table->time('to_time');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // واحد لكل يوم لكل ثيرابست
            $table->unique(['therapist_id', 'day_of_week'], 'chat_avail_unique_day');
            $table->index(['therapist_id', 'is_active'], 'chat_avail_therapist_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_chat_availabilities');
    }
};
