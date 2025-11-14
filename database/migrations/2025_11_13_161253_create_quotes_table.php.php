<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $t) {
            $t->id();
            $t->json('text');                       // { "en":"...", "ar":"..." }
            $t->enum('status', ['active','inactive'])->default('active');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index(['status','sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
