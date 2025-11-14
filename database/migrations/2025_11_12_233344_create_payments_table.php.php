<?php

// database/migrations/2025_11_13_000000_create_payments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('payments', function (Blueprint $t) {
      $t->id();

      $t->foreignId('user_id')->constrained()->cascadeOnDelete();
      $t->foreignId('therapist_id')->nullable()->constrained('therapists')->nullOnDelete();

      // الهدف: جلسة مفردة أو باكيدج
      $t->foreignId('therapy_session_id')->nullable()->constrained('therapy_sessions')->nullOnDelete();
      $t->foreignId('user_package_id')->nullable()->constrained('user_packages')->nullOnDelete();

      $t->enum('purpose', ['single_session','package']);
      $t->integer('amount_cents');        // EGP * 100
      $t->string('currency', 3)->default('EGP');

      $t->enum('provider', ['paymob'])->default('paymob');
      $t->string('provider_order_id')->nullable();
      $t->string('provider_transaction_id')->nullable();

      $t->enum('status', ['pending','paid','failed','refunded'])->default('pending');
      $t->timestamp('paid_at')->nullable();
      $t->timestamp('failed_at')->nullable();
      $t->timestamp('refunded_at')->nullable();

      $t->json('payload')->nullable();   // آخر رد من Paymob (اختياري)

      $t->string('reference')->unique(); // كود مرجعي داخلي لسهولة التتبّع

      $t->timestamps();

      $t->index(['user_id','status']);
      $t->index(['therapist_id']);
      $t->index(['therapy_session_id']);
      $t->index(['user_package_id']);
      $t->index(['provider_order_id']);
    });
  }
  public function down(): void { Schema::dropIfExists('payments'); }
};

