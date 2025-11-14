<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('user_packages', function (Blueprint $t) {
      $t->id();
      $t->foreignId('user_id')->constrained()->cascadeOnDelete();
      $t->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
      $t->foreignId('therapist_id')->nullable()->constrained('therapists')->nullOnDelete();
      $t->unsignedSmallInteger('sessions_total');
      $t->unsignedSmallInteger('sessions_used')->default(0);
      $t->timestamp('purchased_at');
      $t->timestamp('expires_at')->nullable();
      $t->enum('status', ['active','expired','cancelled'])->default('active');

      // هنضيف FK لـ payments لاحقًا بعد ما جدول payments يتخلق
      $t->unsignedBigInteger('payment_id')->nullable();

      $t->timestamps();
      $t->index(['user_id','status']);
    });
  }
  public function down(): void { Schema::dropIfExists('user_packages'); }
};
