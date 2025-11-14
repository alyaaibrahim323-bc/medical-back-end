<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('packages', function (Blueprint $t) {
      $t->id();
      $t->json('name');                    // {"en":"Premium","ar":"..."}
      $t->json('description')->nullable(); // {"en":"...","ar":"..."}
      $t->unsignedSmallInteger('sessions_count');
      $t->unsignedSmallInteger('session_duration_min')->default(45);
      $t->unsignedInteger('price_cents');
      $t->decimal('discount_percent', 5, 2)->default(0);
      $t->char('currency', 3)->default('EGP');
      $t->unsignedSmallInteger('validity_days')->nullable();
      $t->enum('applicability', ['any','therapist'])->default('any');
      $t->boolean('is_active')->default(true);
      $t->timestamps();
      $t->index('is_active');
      $t->index('price_cents');
    });
  }
  public function down(): void { Schema::dropIfExists('packages'); }
};
