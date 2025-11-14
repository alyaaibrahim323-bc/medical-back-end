<?php

// database/migrations/2025_11_08_100000_create_single_session_offers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('single_session_offers', function (Blueprint $t) {
      $t->id();
      $t->foreignId('therapist_id')->constrained('therapists')->cascadeOnDelete();
      $t->unsignedInteger('price_cents');
      $t->char('currency', 3)->default('EGP');
      $t->unsignedSmallInteger('duration_min')->default(45);
      $t->decimal('discount_percent', 5, 2)->default(0); // 0..100
      $t->boolean('is_active')->default(true);
      $t->timestamps();

      $t->unique('therapist_id'); // عرض واحد لكل دكتور (شيليه لو عايزة أكتر من عرض)
      $t->index(['is_active','price_cents']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('single_session_offers');
  }
};

