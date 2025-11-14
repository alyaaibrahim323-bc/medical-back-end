<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('therapy_sessions', function (Blueprint $t) {
      $t->id();
      $t->foreignId('user_id')->constrained()->cascadeOnDelete();
      $t->foreignId('therapist_id')->constrained('therapists')->cascadeOnDelete();

      $t->dateTime('scheduled_at');
      $t->unsignedSmallInteger('duration_min')->default(60);
      $t->enum('status', ['pending_payment','confirmed','completed','cancelled','no_show'])
        ->default('pending_payment');

      // Zoom
      $t->string('zoom_meeting_id')->nullable();
      $t->string('zoom_join_url')->nullable();
      $t->string('zoom_start_url')->nullable();

      // Billing
      $t->foreignId('user_package_id')->nullable()->constrained('user_packages')->nullOnDelete();
      $t->enum('billing_type', ['single','package'])->default('single');
      $t->enum('billing_status', ['pending','covered','paid','failed'])->default('pending');

      $t->text('notes')->nullable();
      $t->timestamps();

      $t->index(['therapist_id','scheduled_at']);
      $t->index(['user_id','scheduled_at']);
      $t->index(['status']);
    });
  }
  public function down(): void { Schema::dropIfExists('therapy_sessions'); }
};
