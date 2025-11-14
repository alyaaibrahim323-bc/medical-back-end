<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('therapist_timeoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained('therapists')->cascadeOnDelete();
            $table->date('off_date');
            $table->string('reason', 120)->nullable();
            $table->timestamps();

            $table->unique(['therapist_id','off_date']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('therapist_timeoffs');
    }
};
