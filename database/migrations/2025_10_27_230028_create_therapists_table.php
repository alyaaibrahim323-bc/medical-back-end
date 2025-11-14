<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('therapists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('specialty')->nullable(); // {"en":"CBT","ar":"..."}
            $table->json('bio')->nullable();       // {"en":"...","ar":"..."}
            $table->unsignedInteger('price_cents')->default(0);
            $table->char('currency', 3)->default('EGP');
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active']);
            $table->index(['price_cents']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('therapists');
    }
};
