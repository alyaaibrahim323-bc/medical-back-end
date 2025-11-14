<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $t) {
            $t->id();
            $t->string('image_path');                 // مسار الصورة داخل storage
            $t->enum('status', ['active','inactive'])->default('active');
            $t->unsignedInteger('sort_order')->default(0); // للترتيب بس
            $t->timestamps();

            $t->index(['status','sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
